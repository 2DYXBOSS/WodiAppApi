<?php

namespace App\Services;

use App\Jobs\SendExpoPush;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\User;
use App\Notifications\AdminEventMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifications in-app (table `notifications`), push Expo (file d'attente, après validation
 * de la transaction) et e-mails aux administrateurs selon les paramètres du salon.
 *
 * Le push n'est jamais considéré comme envoyé si Expo n'est pas configuré ou a échoué.
 */
class Notifier
{
    /** Préférence utilisateur qui conditionne le push pour chaque type. */
    private const PREFERENCE = [
        'booking_reminder' => 'notify_reminders',
        'promotion' => 'notify_promotions',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function toUser(?User $user, string $type, string $title, string $message, ?string $bookingId = null, ?string $link = null): void
    {
        if (! $user || ! $user->is_active) {
            return;
        }
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => ['type' => $type, 'title' => $title, 'message' => $message, 'bookingId' => $bookingId, 'link' => $link],
        ]);

        $preference = self::PREFERENCE[$type] ?? 'notify_booking_updates';
        if (config('wodi.expo.enabled') && $user->{$preference} && $user->deviceTokens()->exists()) {
            SendExpoPush::dispatch($user->id, $title, $message, array_filter(['bookingId' => $bookingId, 'type' => $type]))->afterCommit();
        }
    }

    public function toCustomer(?Customer $customer, string $type, string $title, string $message, ?string $bookingId = null): void
    {
        $this->toUser($customer?->user, $type, $title, $message, $bookingId);
    }

    public function toProfessional(?Professional $professional, string $type, string $title, string $message, ?string $bookingId = null): void
    {
        $this->toUser($professional?->user, $type, $title, $message, $bookingId);
    }

    /**
     * @param  string|null  $emailEvent  clé de `notifications.events` (newReservation, cancellation, payment, newClient),
     *                                   `contact`, ou null (aucun e-mail)
     */
    public function toAdmins(string $type, string $title, string $message, ?string $link = null, ?string $emailEvent = null): void
    {
        $admins = User::permission('notifications.view')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            $this->toUser($admin, $type, $title, $message, null, $link);
        }

        $settings = $this->settings->get('notifications');
        $sendEmail = $settings['channels']['email'] ?? false;
        // `contact` (message du site) : toujours envoyé si le canal e-mail est actif.
        if ($sendEmail && $emailEvent !== null && ($emailEvent === 'contact' || ($settings['events'][$emailEvent] ?? false))) {
            foreach ($admins as $admin) {
                try {
                    $admin->notify((new AdminEventMail($title, $message, $link))->afterCommit());
                } catch (\Throwable $e) {
                    // L'échec d'un e-mail n'annule jamais l'opération métier ; il est journalisé.
                    Log::warning('Notification e-mail administrateur non envoyée', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
