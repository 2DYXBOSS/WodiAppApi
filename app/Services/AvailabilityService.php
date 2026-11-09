<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\BookingStatus;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\Professional;
use App\Models\SalonClosure;
use App\Models\ScheduleBreak;
use App\Models\Service;
use App\Models\Unavailability;
use App\Models\WorkingHour;
use App\Support\SalonClock;
use Illuminate\Support\Collection;

/**
 * Moteur de disponibilités (source unique pour le mobile, le back-office et la vitrine).
 *
 * Un créneau est libre si : salon ouvert ce jour (hors jours exceptionnels fermés), jour travaillé
 * du professionnel, dans l'intersection horaires salon ∩ professionnel, hors pauses, indisponibilités,
 * absences (en attente ou validées) et réservations bloquantes, professionnel actif et habilité.
 * Pour les clients s'ajoutent : délai minimal de prévenance et fenêtre de réservation.
 *
 * Toutes les données d'une période sont chargées en une fois (pas de requêtes N+1).
 */
class AvailabilityService
{
    public function __construct(private readonly SettingsService $settings) {}

    /** Professionnels actifs, réservables et habilités (prestation activée) pour une prestation. */
    public function eligibleProfessionals(Service $service, ?string $onlyId = null): Collection
    {
        return Professional::query()
            ->active()
            ->where('is_bookable', true)
            ->when($onlyId, fn ($q) => $q->whereKey($onlyId))
            ->whereHas('services', fn ($q) => $q->whereKey($service->id)->where('professional_service.is_active', true))
            ->orderBy('sort_order')
            ->get();
    }

    /** Charge en une fois toutes les contraintes de planning d'une période. */
    public function context(array $professionalIds, string $from, string $to, ?string $excludeBookingId = null): array
    {
        [$startUtc, $endUtc] = SalonClock::dayBounds($from, $to);

        $working = WorkingHour::query()->whereIn('professional_id', $professionalIds)->get()
            ->groupBy('professional_id')->map(fn ($rows) => $rows->keyBy('iso_day'));
        $breaks = ScheduleBreak::query()->whereIn('professional_id', $professionalIds)->get()
            ->groupBy(fn ($b) => $b->professional_id.'|'.$b->iso_day);
        $unavailabilities = Unavailability::query()->whereIn('professional_id', $professionalIds)
            ->whereBetween('date', [$from, $to])->get()
            ->groupBy(fn ($u) => $u->professional_id.'|'.$u->date->toDateString());
        $absences = Absence::query()->whereIn('professional_id', $professionalIds)
            ->where('status', '!=', ApprovalStatus::Rejected->value)
            ->where('start_date', '<=', $to)->where('end_date', '>=', $from)->get()
            ->groupBy('professional_id');
        $bookings = Booking::query()->whereIn('professional_id', $professionalIds)
            ->whereIn('status', BookingStatus::values(BookingStatus::blocking()))
            ->where('starts_at', '<', $endUtc)->where('ends_at', '>', $startUtc)
            ->when($excludeBookingId, fn ($q) => $q->whereKeyNot($excludeBookingId))
            ->get(['id', 'professional_id', 'starts_at', 'ends_at', 'reference'])
            ->groupBy(fn ($b) => $b->professional_id.'|'.SalonClock::localDate($b->starts_at));

        return [
            'salonHours' => $this->settings->salonHours(),
            'closed' => SalonClosure::query()->where('is_closed', true)->whereBetween('date', [$from, $to])
                ->pluck('date')->map(fn ($d) => $d->toDateString())->flip()->all(),
            'working' => $working,
            'breaks' => $breaks,
            'unavailabilities' => $unavailabilities,
            'absences' => $absences,
            'bookings' => $bookings,
        ];
    }

    /** Raison pour laquelle le professionnel ne travaille pas ce jour-là, ou null + fenêtre. */
    public function window(array $ctx, string $professionalId, string $date): array
    {
        if (isset($ctx['closed'][$date])) {
            return [null, 'Le salon est fermé ce jour-là.'];
        }
        $isoDay = SalonClock::isoDay($date);
        $salon = $ctx['salonHours'][$isoDay] ?? null;
        if (! $salon || $salon['closed']) {
            return [null, 'Le salon est fermé ce jour-là.'];
        }
        $day = $ctx['working'][$professionalId][$isoDay] ?? null;
        if (! $day || ! $day->is_working || ! $day->start_time || ! $day->end_time) {
            return [null, 'Le professionnel ne travaille pas ce jour-là.'];
        }
        foreach ($ctx['absences'][$professionalId] ?? [] as $absence) {
            if ($absence->start_date->toDateString() <= $date && $date <= $absence->end_date->toDateString()) {
                return [null, 'Le professionnel est absent à cette date ('.$absence->type->label().').'];
            }
        }
        $start = max(SalonClock::minutes($salon['open']), SalonClock::minutes($day->start_time));
        $end = min(SalonClock::minutes($salon['close']), SalonClock::minutes($day->end_time));

        return $start < $end ? [['start' => $start, 'end' => $end], null] : [null, 'Aucun horaire commun avec le salon ce jour-là.'];
    }

    /** Intervalles occupés (minutes locales) : pauses, indisponibilités, réservations. */
    public function blocked(array $ctx, string $professionalId, string $date): array
    {
        $intervals = [];
        foreach ($ctx['breaks'][$professionalId.'|'.SalonClock::isoDay($date)] ?? [] as $b) {
            $intervals[] = ['start' => SalonClock::minutes($b->start_time), 'end' => SalonClock::minutes($b->end_time), 'kind' => 'break'];
        }
        foreach ($ctx['unavailabilities'][$professionalId.'|'.$date] ?? [] as $u) {
            $intervals[] = ['start' => SalonClock::minutes($u->start_time), 'end' => SalonClock::minutes($u->end_time), 'kind' => 'unavailability'];
        }
        foreach ($ctx['bookings'][$professionalId.'|'.$date] ?? [] as $bk) {
            $start = SalonClock::minutes(SalonClock::localTime($bk->starts_at));
            $intervals[] = [
                'start' => $start,
                'end' => $start + (int) $bk->starts_at->diffInMinutes($bk->ends_at),
                'kind' => 'booking',
                'reference' => $bk->reference,
            ];
        }

        return $intervals;
    }

    /** null si le créneau est libre, sinon un message explicite. */
    public function conflict(array $ctx, string $professionalId, string $date, int $start, int $duration): ?string
    {
        [$window, $reason] = $this->window($ctx, $professionalId, $date);
        if (! $window) {
            return $reason;
        }
        $end = $start + $duration;
        if ($start < $window['start'] || $end > $window['end']) {
            return sprintf('Hors des horaires de travail (%s – %s).', SalonClock::fromMinutes($window['start']), SalonClock::fromMinutes($window['end']));
        }
        foreach ($this->blocked($ctx, $professionalId, $date) as $i) {
            if ($start < $i['end'] && $i['start'] < $end) {
                return match ($i['kind']) {
                    'break' => 'Ce créneau chevauche une pause du professionnel.',
                    'unavailability' => 'Le professionnel est indisponible sur ce créneau.',
                    default => 'Créneau déjà pris (réservation '.$i['reference'].').',
                };
            }
        }

        return null;
    }

    /**
     * Créneaux réservables par un client. `$professionalId` null = premier professionnel disponible
     * (le moins chargé ce jour-là est proposé en priorité).
     *
     * @return list<array{time:string,staffId:string}>
     */
    public function slots(Service $service, ?string $professionalId, string $date, ?string $excludeBookingId = null, ?array $ctx = null, ?Collection $candidates = null): array
    {
        $rules = $this->settings->booking();
        if (! $service->isBookable() || ! $this->withinBookingWindow($date, (int) $rules['bookingWindowDays'])) {
            return [];
        }
        $candidates ??= $this->eligibleProfessionals($service, $professionalId);
        if ($candidates->isEmpty()) {
            return [];
        }
        $ctx ??= $this->context($candidates->modelKeys(), $date, $date, $excludeBookingId);
        $duration = (int) $service->duration_minutes;
        $step = max(5, (int) $rules['slotStepMinutes']);
        $earliest = SalonClock::now()->addHours((int) $rules['minNoticeHours']);

        $ordered = $candidates->sortBy(fn (Professional $p) => count($ctx['bookings'][$p->id.'|'.$date] ?? []))->values();
        $byTime = [];
        foreach ($ordered as $professional) {
            [$window] = $this->window($ctx, $professional->id, $date);
            if (! $window) {
                continue;
            }
            for ($start = $window['start']; $start + $duration <= $window['end']; $start += $step) {
                $time = SalonClock::fromMinutes($start);
                if (isset($byTime[$time]) || SalonClock::toUtc($date, $time)->lt($earliest)) {
                    continue;
                }
                if ($this->conflict($ctx, $professional->id, $date, $start, $duration) === null) {
                    $byTime[$time] = $professional->id;
                }
            }
        }
        ksort($byTime);

        return array_map(fn ($time, $id) => ['time' => (string) $time, 'staffId' => $id], array_keys($byTime), $byTime);
    }

    /** @return list<array{date:string,available:bool}> */
    public function days(Service $service, ?string $professionalId, string $from, string $to, ?string $excludeBookingId = null): array
    {
        $candidates = $this->eligibleProfessionals($service, $professionalId);
        $ctx = $candidates->isEmpty() ? null : $this->context($candidates->modelKeys(), $from, $to, $excludeBookingId);
        $result = [];
        for ($date = $from; $date <= $to; $date = SalonClock::addDays($date, 1)) {
            $result[] = [
                'date' => $date,
                'available' => $ctx !== null && $this->slots($service, $professionalId, $date, $excludeBookingId, $ctx, $candidates) !== [],
            ];
        }

        return $result;
    }

    public function withinBookingWindow(string $date, int $windowDays): bool
    {
        $offset = SalonClock::daysBetween(SalonClock::today(), $date);

        return $offset >= 0 && $offset <= $windowDays;
    }
}
