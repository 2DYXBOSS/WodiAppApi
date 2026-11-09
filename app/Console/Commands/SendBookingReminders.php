<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Notifier;
use App\Support\SalonClock;
use Illuminate\Console\Command;

/** Rappel ~24 h avant le rendez-vous (client et professionnel). Planifié toutes les 15 minutes. */
class SendBookingReminders extends Command
{
    protected $signature = 'wodi:send-reminders';

    protected $description = 'Envoie les rappels de rendez-vous à J-1';

    public function handle(Notifier $notifier): int
    {
        $count = 0;
        Booking::query()->where('status', BookingStatus::Confirmed->value)->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now()->addHours(2), now()->addHours(24)])
            ->with(['customer.user', 'professional.user'])->chunkById(100, function ($bookings) use ($notifier, &$count) {
                foreach ($bookings as $booking) {
                    // Marqué avant l'envoi : aucun rappel en double même si un envoi échoue (le push est rejoué par la file).
                    $booking->update(['reminder_sent_at' => now()]);
                    $when = SalonClock::localDate($booking->starts_at).' à '.SalonClock::localTime($booking->starts_at);
                    $notifier->toCustomer($booking->customer, 'booking_reminder', 'Rappel de rendez-vous',
                        "{$booking->service_name_snapshot} le {$when} ({$booking->reference}).", $booking->id);
                    $notifier->toProfessional($booking->professional, 'booking_reminder', 'Rendez-vous à venir',
                        "{$booking->service_name_snapshot} — {$booking->customer->fullName()} le {$when}.", $booking->id);
                    $count++;
                }
            });
        $this->info("{$count} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
