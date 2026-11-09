<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\SalonClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Règles de réservation centralisées (client mobile, professionnel, back-office).
 *
 * Anti double réservation, en trois niveaux :
 *  1. verrou `FOR UPDATE` sur les lignes des professionnels concernés (sérialise les écritures concurrentes) ;
 *  2. recalcul de la disponibilité DANS la transaction ;
 *  3. contrainte d'exclusion PostgreSQL `bookings_no_overlap` (garantie ultime) → HTTP 409.
 */
class BookingService
{
    public const SLOT_TAKEN = 'Ce créneau vient d’être réservé ou n’est plus disponible. Choisissez-en un autre.';

    /** Actions de statut → statut cible (identiques au back-office et au mobile). */
    public const ACTIONS = [
        'confirm' => BookingStatus::Confirmed,
        'decline' => BookingStatus::Cancelled,
        'start' => BookingStatus::InProgress,
        'complete' => BookingStatus::Completed,
        'no_show' => BookingStatus::NoShow,
        'cancel' => BookingStatus::Cancelled,
    ];

    private const ACTION_LABELS = [
        'confirm' => 'Confirmée',
        'decline' => 'Refusée',
        'start' => 'Prestation commencée',
        'complete' => 'Prestation terminée',
        'no_show' => 'Absence signalée',
        'cancel' => 'Annulée',
    ];

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly SettingsService $settings,
        private readonly Notifier $notifier,
    ) {}

    // ------------------------------------------------------------- Création

    /**
     * Réservation par un client (mobile) : créneau proposé par le moteur, délai de prévenance,
     * fenêtre de réservation, professionnel « premier disponible » si autorisé.
     */
    public function createForClient(Customer $customer, User $actor, array $input): Booking
    {
        $service = $this->bookableService($input['serviceId']);
        $location = $this->location($service, $input['location'] ?? 'salon');
        $staffId = $this->staffChoice($input['staffId'] ?? null);

        $booking = $this->inTransaction(function () use ($service, $staffId, $customer, $actor, $input, $location) {
            $slot = $this->lockAndFindSlot($service, $staffId, $input['date'], $input['time']);

            return $this->insert($customer, $service, $slot['staffId'], $input['date'], $input['time'], $location,
                $input['notes'] ?? null, $actor, 'Réservation créée');
        });

        $this->notifyCreated($booking);

        return $booking;
    }

    /** Réservation saisie dans le back-office (pas de délai de prévenance, contrôles de planning complets). */
    public function createForAdmin(User $actor, array $input): Booking
    {
        $customer = Customer::query()->find($input['clientId'])
            ?? throw ApiException::unprocessable('Client introuvable.', ['clientId' => 'Client introuvable.']);
        $service = $this->adminService($input['serviceId']);
        $professional = $this->adminProfessional($input['staffId'], $service);
        $location = $this->location($service, $input['location'] ?? 'salon');
        $this->assertNotPast($input['date'], $input['time']);

        $booking = $this->inTransaction(function () use ($customer, $service, $professional, $input, $location, $actor) {
            $this->lockProfessionals([$professional->id]);
            $this->assertAdminSlot($professional, $input['date'], $input['time'], (int) $service->duration_minutes, null);

            return $this->insert($customer, $service, $professional->id, $input['date'], $input['time'], $location,
                $input['notes'] ?? null, $actor, 'Création');
        });

        $this->notifyCreated($booking);

        return $booking;
    }

    // --------------------------------------------------------- Modification

    public function rescheduleForClient(Booking $booking, User $actor, array $input): Booking
    {
        if ($reason = $this->clientCancelBlockedReason($booking)) {
            throw ApiException::conflict($reason);
        }
        $service = $this->bookableService($booking->service_id);
        $staffId = $this->staffChoice($input['staffId'] ?? null);
        $previous = $this->slotLabel($booking);
        $previousStaff = $booking->professional_id;

        $booking = $this->inTransaction(function () use ($booking, $service, $staffId, $input, $actor, $previous) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $slot = $this->lockAndFindSlot($service, $staffId, $input['date'], $input['time'], $locked->id);
            $this->moveTo($locked, $slot['staffId'], $input['date'], $input['time'], (int) $locked->duration_minutes);
            $old = $locked->status;
            $locked->status = $this->initialStatus();
            $locked->save();
            $this->history($locked, $actor, 'Modifiée par le client', 'Ancien créneau : '.$previous, $old);

            return $locked;
        });

        $this->notifier->toProfessional($booking->professional, 'booking_updated', 'Réservation modifiée',
            "{$booking->reference} : nouveau créneau {$this->slotLabel($booking)}.", $booking->id);
        if ($previousStaff !== $booking->professional_id) {
            $this->notifier->toProfessional(Professional::query()->find($previousStaff), 'booking_updated', 'Réservation déplacée',
                "{$booking->reference} a été attribuée à un autre professionnel.", $booking->id);
        }

        return $booking;
    }

    /** Modification complète depuis le back-office (`PATCH /admin/reservations/:id`). */
    public function updateForAdmin(Booking $booking, User $actor, array $input): Booking
    {
        if (in_array($booking->status, [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
            throw ApiException::conflict('Une réservation terminée, annulée ou en absence ne peut plus être modifiée.');
        }

        return $this->inTransaction(function () use ($booking, $actor, $input) {
            $locked = Booking::query()->with(['service', 'professional'])->lockForUpdate()->findOrFail($booking->id);
            $service = isset($input['serviceId']) && $input['serviceId'] !== $locked->service_id
                ? $this->adminService($input['serviceId']) : $locked->service;
            $professional = $this->adminProfessional($input['staffId'] ?? $locked->professional_id, $service);
            $date = $input['date'] ?? SalonClock::localDate($locked->starts_at);
            $time = $input['time'] ?? SalonClock::localTime($locked->starts_at);
            $changes = [];

            $slotChanged = $date !== SalonClock::localDate($locked->starts_at) || $time !== SalonClock::localTime($locked->starts_at);
            if ($slotChanged || $professional->id !== $locked->professional_id || $service->id !== $locked->service_id) {
                if ($slotChanged) {
                    $this->assertNotPast($date, $time);
                    $changes[] = 'créneau '.$this->slotLabel($locked)." → {$date} {$time}";
                }
                if ($professional->id !== $locked->professional_id) {
                    $changes[] = 'professionnel → '.$professional->fullName();
                }
                $duration = (int) ($service->id !== $locked->service_id ? $service->duration_minutes : $locked->duration_minutes);
                $this->lockProfessionals(array_unique([$locked->professional_id, $professional->id]));
                $this->assertAdminSlot($professional, $date, $time, $duration, $locked->id);
                $this->moveTo($locked, $professional->id, $date, $time, $duration);
            }
            if ($service->id !== $locked->service_id) {
                $changes[] = 'prestation → '.$service->name;
                $locked->service_id = $service->id;
                $locked->service_name_snapshot = $service->name;
                $locked->price_snapshot = $service->price;
            }
            if (array_key_exists('location', $input)) {
                $locked->location = $this->location($service, $input['location']);
            }
            if (array_key_exists('notes', $input)) {
                $locked->customer_note = $input['notes'];
            }
            if (isset($input['clientId']) && $input['clientId'] !== $locked->customer_id) {
                throw ApiException::unprocessable('Le client d’une réservation ne peut pas être changé.', ['clientId' => 'Non modifiable.']);
            }
            $locked->payment_status = PaymentStatus::forAmounts($locked->price_snapshot, $locked->amount_paid);
            $locked->save();
            $this->history($locked, $actor, 'Modification', implode(', ', $changes) ?: null, $locked->status);

            return $locked;
        });
    }

    // --------------------------------------------------------------- Statuts

    public function cancelForClient(Booking $booking, User $actor, ?string $reason): Booking
    {
        if ($blocked = $this->clientCancelBlockedReason($booking)) {
            throw ApiException::conflict($blocked);
        }
        $booking = $this->applyStatus($booking, $actor, 'cancel', $reason, 'Annulée par le client', $reason ?: 'Annulée par le client');
        $this->notifier->toCustomer($booking->customer, 'booking_cancelled', 'Réservation annulée',
            "Votre réservation {$booking->reference} est annulée.", $booking->id);
        $this->notifier->toProfessional($booking->professional, 'booking_cancelled', 'Réservation annulée',
            "{$booking->reference} a été annulée par le client.", $booking->id);
        $this->notifier->toAdmins('reservation_cancelled', 'Réservation annulée',
            "{$booking->reference} — {$booking->customer->fullName()} (annulée par le client)", $this->adminLink($booking), 'cancellation');

        return $booking;
    }

    /** Action d'un professionnel (`/pro/bookings/:id/<action>`), contrôlée par `allowedActions`. */
    public function actionForProfessional(Booking $booking, User $actor, string $action, ?string $reason): Booking
    {
        if (! in_array($action, $this->allowedActions($booking, $actor), true)) {
            $permission = in_array($action, ['confirm', 'decline'], true) ? 'bookings.respond' : 'bookings.update_status';
            if (! $actor->can($permission)) {
                throw ApiException::forbidden();
            }
            throw ApiException::conflict('Action impossible pour le statut actuel de cette réservation.');
        }

        $booking = $this->applyStatus($booking, $actor, $action, $reason, self::ACTION_LABELS[$action],
            $action === 'decline' ? ($reason ?: 'Refusée par le salon') : null);
        $this->notifyStatus($booking, $action);

        return $booking;
    }

    /** Action du back-office (`/admin/reservations/:id/<action>`). */
    public function actionForAdmin(Booking $booking, User $actor, string $action, ?string $reason): Booking
    {
        if ($action === 'cancel' && ! $reason) {
            throw ApiException::unprocessable('Indiquez le motif d’annulation.', ['reason' => 'Motif requis.']);
        }
        $booking = $this->applyStatus($booking, $actor, $action, $reason, self::ACTION_LABELS[$action], $action === 'cancel' ? $reason : null);
        $this->notifyStatus($booking, $action);

        return $booking;
    }

    private function applyStatus(Booking $booking, User $actor, string $action, ?string $detail, string $label, ?string $cancellationReason): Booking
    {
        $target = self::ACTIONS[$action];

        $booking = $this->inTransaction(function () use ($booking, $actor, $target, $detail, $label, $cancellationReason) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ApiException::conflict('Ce changement de statut n’est pas autorisé pour cette réservation.');
            }
            $old = $locked->status;
            $locked->status = $target;
            if ($target === BookingStatus::Cancelled) {
                $locked->cancelled_at = now();
                $locked->cancellation_reason = $cancellationReason;
            }
            $locked->save();
            $this->history($locked, $actor, $label, $detail, $old);

            if ($target === BookingStatus::Completed) {
                LoyaltyTransaction::query()->firstOrCreate(['booking_id' => $locked->id], [
                    'customer_id' => $locked->customer_id,
                    'type' => 'visit',
                    'points' => 0,
                    'label' => 'Visite — '.$locked->service_name_snapshot,
                    'occurred_on' => SalonClock::localDate($locked->starts_at),
                    'created_by' => $actor->id,
                ]);
            }

            return $locked;
        });

        return $booking->load(['customer.user', 'professional.user', 'service']);
    }

    // ------------------------------------------------------- Règles affichées

    /** Raison pour laquelle un client ne peut plus annuler/modifier (null = possible). */
    public function clientCancelBlockedReason(Booking $booking): ?string
    {
        if (! in_array($booking->status, BookingStatus::active(), true)) {
            return 'Cette réservation ne peut plus être modifiée.';
        }
        $hours = (int) $this->settings->booking()['cancellationNoticeHours'];
        if (now()->diffInMinutes($booking->starts_at, false) < $hours * 60) {
            return "Modification et annulation possibles jusqu’à {$hours} h avant le rendez-vous. Contactez le salon.";
        }

        return null;
    }

    /** Actions autorisées pour l'utilisateur courant (calculées par le serveur). */
    public function allowedActions(Booking $booking, User $viewer): array
    {
        if ($viewer->isClient()) {
            return $booking->customer?->user_id === $viewer->id && $this->clientCancelBlockedReason($booking) === null
                ? ['cancel', 'reschedule'] : [];
        }
        $professional = $viewer->isBarber() ? $viewer->professional : null;
        if (! $professional || $booking->professional_id !== $professional->id) {
            return [];
        }
        $actions = [];
        $started = now()->gte($booking->starts_at);
        $isToday = SalonClock::localDate($booking->starts_at) === SalonClock::today();
        if ($booking->status === BookingStatus::Pending && $viewer->can('bookings.respond')) {
            array_push($actions, 'confirm', 'decline');
        }
        if ($booking->status === BookingStatus::Confirmed && $viewer->can('bookings.update_status')) {
            if ($isToday) {
                $actions[] = 'start';
            }
            if ($started) {
                array_push($actions, 'complete', 'no_show');
            }
        }
        if ($booking->status === BookingStatus::InProgress && $viewer->can('bookings.update_status')) {
            $actions[] = 'complete';
        }

        return $actions;
    }

    // -------------------------------------------------------------- Interne

    private function inTransaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (QueryException $e) {
            // 23P01 = violation de la contrainte d'exclusion (chevauchement concurrent).
            if (($e->errorInfo[0] ?? null) === '23P01') {
                throw ApiException::conflict(self::SLOT_TAKEN, ['time' => 'Créneau occupé.']);
            }
            throw $e;
        }
    }

    /** Verrouille les professionnels dans un ordre stable (évite les interblocages). */
    private function lockProfessionals(array $ids): void
    {
        sort($ids);
        Professional::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** Verrouille les candidats puis revérifie le créneau demandé avec les données à jour. */
    private function lockAndFindSlot(Service $service, ?string $staffId, string $date, string $time, ?string $excludeBookingId = null): array
    {
        $candidates = $this->availability->eligibleProfessionals($service, $staffId);
        if ($staffId && $candidates->isEmpty()) {
            throw ApiException::conflict('Ce professionnel ne réalise pas cette prestation.', ['staffId' => 'Professionnel non disponible.']);
        }
        $this->lockProfessionals($candidates->modelKeys());
        $ctx = $this->availability->context($candidates->modelKeys(), $date, $date, $excludeBookingId);
        $slot = collect($this->availability->slots($service, $staffId, $date, $excludeBookingId, $ctx, $candidates))
            ->firstWhere('time', $time);

        return $slot ?? throw ApiException::conflict(self::SLOT_TAKEN, ['time' => 'Créneau indisponible.']);
    }

    private function assertAdminSlot(Professional $professional, string $date, string $time, int $duration, ?string $excludeBookingId): void
    {
        $ctx = $this->availability->context([$professional->id], $date, $date, $excludeBookingId);
        $conflict = $this->availability->conflict($ctx, $professional->id, $date, SalonClock::minutes($time), $duration);
        if ($conflict !== null) {
            throw ApiException::conflict($conflict, ['time' => 'Créneau indisponible.']);
        }
    }

    private function insert(Customer $customer, Service $service, string $professionalId, string $date, string $time,
        string $location, ?string $notes, User $actor, string $label): Booking
    {
        $startsAt = SalonClock::toUtc($date, $time);
        $booking = Booking::query()->create([
            'customer_id' => $customer->id,
            'professional_id' => $professionalId,
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes((int) $service->duration_minutes),
            'duration_minutes' => (int) $service->duration_minutes,
            'location' => $location,
            'service_name_snapshot' => $service->name,
            'price_snapshot' => $service->price,
            'amount_paid' => 0,
            'status' => $this->initialStatus(),
            'payment_status' => PaymentStatus::Pending,
            'customer_note' => $notes ?: null,
            'created_by' => $actor->id,
        ]);
        $this->history($booking, $actor, $label, null, null);

        return $booking->load(['customer.user', 'professional.user', 'service']);
    }

    private function moveTo(Booking $booking, string $professionalId, string $date, string $time, int $duration): void
    {
        $startsAt = SalonClock::toUtc($date, $time);
        $booking->professional_id = $professionalId;
        $booking->starts_at = $startsAt;
        $booking->ends_at = $startsAt->addMinutes($duration);
        $booking->duration_minutes = $duration;
        $booking->reminder_sent_at = null;
    }

    private function history(Booking $booking, User $actor, string $action, ?string $detail, ?BookingStatus $old): void
    {
        $booking->histories()->create([
            'old_status' => $old?->value,
            'new_status' => $booking->status->value,
            'action' => $action,
            'detail' => $detail,
            'changed_by' => $actor->id,
            'changed_by_name' => $actor->fullName(),
        ]);
    }

    private function initialStatus(): BookingStatus
    {
        return $this->settings->booking()['autoConfirm'] ? BookingStatus::Confirmed : BookingStatus::Pending;
    }

    private function bookableService(string $serviceId): Service
    {
        $service = Service::query()->find($serviceId) ?? throw ApiException::notFound('Prestation introuvable.');
        if (! $service->isBookable()) {
            throw ApiException::conflict('Cette prestation n’est pas réservable en ligne.');
        }

        return $service;
    }

    private function adminService(string $serviceId): Service
    {
        $service = Service::query()->find($serviceId);
        if (! $service || ! $service->is_active) {
            throw ApiException::unprocessable('Prestation indisponible.', ['serviceId' => 'Prestation inactive.']);
        }
        if (! $service->duration_minutes) {
            throw ApiException::unprocessable('Cette prestation n’a pas de durée définie : elle ne peut pas être réservée.', ['serviceId' => 'Durée non définie.']);
        }

        return $service;
    }

    private function adminProfessional(string $staffId, Service $service): Professional
    {
        $professional = Professional::query()->find($staffId);
        if (! $professional || ! $professional->is_active) {
            throw ApiException::unprocessable('Professionnel indisponible.', ['staffId' => 'Professionnel inactif.']);
        }
        if (! $professional->services()->whereKey($service->id)->exists()) {
            throw ApiException::unprocessable('Ce professionnel ne réalise pas cette prestation.', ['staffId' => 'Prestation non réalisée.']);
        }

        return $professional;
    }

    /** `null`/`any` = premier disponible, seulement si le salon l'autorise. */
    private function staffChoice(?string $staffId): ?string
    {
        if ($staffId === null || $staffId === '' || $staffId === 'any') {
            if (! $this->settings->booking()['allowAnyStaff']) {
                throw ApiException::unprocessable('Veuillez choisir un professionnel.', ['staffId' => 'Professionnel requis.']);
            }

            return null;
        }

        return $staffId;
    }

    private function location(Service $service, string $location): string
    {
        if (! in_array($location, $service->locations(), true)) {
            throw ApiException::unprocessable('Lieu non proposé pour cette prestation.', ['location' => 'Lieu non proposé.']);
        }

        return $location;
    }

    private function assertNotPast(string $date, string $time): void
    {
        if (SalonClock::toUtc($date, $time)->lt(now())) {
            throw ApiException::unprocessable('Impossible de créer une réservation dans le passé.', ['date' => 'Date passée.']);
        }
    }

    private function slotLabel(Booking $booking): string
    {
        return SalonClock::localDate($booking->starts_at).' '.SalonClock::localTime($booking->starts_at);
    }

    public function adminLink(Booking $booking): string
    {
        return '/admin/reservations?ref='.$booking->reference;
    }

    private function notifyCreated(Booking $booking): void
    {
        $confirmed = $booking->status === BookingStatus::Confirmed;
        $this->notifier->toCustomer($booking->customer,
            $confirmed ? 'booking_confirmed' : 'booking_received',
            $confirmed ? 'Réservation confirmée' : 'Demande reçue',
            $confirmed
                ? "Votre réservation {$booking->reference} est confirmée pour le {$this->slotLabel($booking)}."
                : "Votre demande {$booking->reference} a bien été reçue.",
            $booking->id);
        $this->notifier->toProfessional($booking->professional,
            $confirmed ? 'booking_received' : 'booking_request',
            $confirmed ? 'Nouvelle réservation' : 'Nouvelle demande',
            "{$booking->service_name_snapshot} — {$booking->reference} ({$this->slotLabel($booking)})", $booking->id);
        $this->notifier->toAdmins('reservation_created', 'Nouvelle réservation',
            "{$booking->reference} — {$booking->customer->fullName()}, {$booking->service_name_snapshot}",
            $this->adminLink($booking), 'newReservation');
    }

    private function notifyStatus(Booking $booking, string $action): void
    {
        $label = "{$booking->service_name_snapshot} — {$booking->reference}";
        match ($action) {
            'confirm' => $this->notifier->toCustomer($booking->customer, 'booking_confirmed', 'Réservation confirmée', $label, $booking->id),
            'decline', 'cancel' => $this->notifier->toCustomer($booking->customer, 'booking_cancelled',
                $action === 'decline' ? 'Demande non retenue' : 'Réservation annulée', $label, $booking->id),
            'start' => $this->notifier->toCustomer($booking->customer, 'booking_updated', 'Votre prestation a commencé', $label, $booking->id),
            'complete' => $this->notifier->toCustomer($booking->customer, 'booking_updated', 'Merci de votre visite', $label, $booking->id),
            'no_show' => $this->notifier->toCustomer($booking->customer, 'booking_updated', 'Absence au rendez-vous', $label, $booking->id),
        };
        if ($action === 'cancel') {
            $this->notifier->toProfessional($booking->professional, 'booking_cancelled', 'Réservation annulée', $label, $booking->id);
        }
        if (in_array($action, ['confirm', 'decline', 'cancel'], true)) {
            $cancelled = $action !== 'confirm';
            $this->notifier->toAdmins($cancelled ? 'reservation_cancelled' : 'reservation_confirmed',
                $cancelled ? 'Réservation annulée' : 'Réservation confirmée',
                "{$booking->reference} — {$booking->customer->fullName()}", $this->adminLink($booking), $cancelled ? 'cancellation' : null);
        }
    }

    /** @return Collection<int,Booking> réservations actives d'un professionnel sur une période */
    public function activeBookingsBetween(string $professionalId, string $from, string $to): Collection
    {
        [$start, $end] = SalonClock::dayBounds($from, $to);

        return Booking::query()->where('professional_id', $professionalId)
            ->whereIn('status', BookingStatus::values(BookingStatus::active()))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->orderBy('starts_at')->get();
    }
}
