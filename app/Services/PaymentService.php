<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Paiements V1 : enregistrement MANUEL (encaissement au salon), partiel ou total.
 * Aucun paiement en ligne n'est simulé : l'intégration d'un prestataire (Orange Money,
 * MTN MoMo, Wave…) passera par ses webhooks signés, idempotents via `transaction_reference`.
 */
class PaymentService
{
    /** Fenêtre de détection d'un double clic (même réservation, montant et mode). */
    private const DUPLICATE_WINDOW_SECONDS = 120;

    public function __construct(private readonly Notifier $notifier) {}

    public function recordManual(User $actor, string $bookingId, int $amount, PaymentMethod $method, ?string $note): Payment
    {
        $payment = DB::transaction(function () use ($actor, $bookingId, $amount, $method, $note) {
            $booking = Booking::query()->lockForUpdate()->find($bookingId)
                ?? throw ApiException::unprocessable('Réservation introuvable.', ['reservationId' => 'Réservation introuvable.']);

            if ($booking->status === BookingStatus::Cancelled) {
                throw ApiException::conflict('Impossible d’encaisser une réservation annulée.');
            }
            if ($booking->price_snapshot === null) {
                throw ApiException::conflict('Le tarif de cette réservation n’est pas défini : paiement impossible.');
            }
            $remaining = $booking->remainingAmount();
            if ($remaining <= 0) {
                throw ApiException::conflict('Cette réservation est déjà entièrement payée.');
            }
            if ($amount > $remaining) {
                throw ApiException::unprocessable("Le montant dépasse le reste à payer ({$remaining} F CFA).", ['amount' => "Maximum : {$remaining} F CFA."]);
            }
            $duplicate = Payment::query()->where('booking_id', $booking->id)->where('amount', $amount)
                ->where('method', $method->value)->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))->exists();
            if ($duplicate) {
                throw ApiException::conflict('Un paiement identique vient d’être enregistré pour cette réservation.');
            }

            $booking->amount_paid += $amount;
            $booking->payment_status = PaymentStatus::forAmounts($booking->price_snapshot, $booking->amount_paid);
            $booking->save();
            $booking->histories()->create([
                'old_status' => $booking->status->value,
                'new_status' => $booking->status->value,
                'action' => 'Paiement enregistré',
                'detail' => "{$amount} F CFA ({$method->value})",
                'changed_by' => $actor->id,
                'changed_by_name' => $actor->fullName(),
            ]);

            return Payment::query()->create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'amount' => $amount,
                'currency' => config('wodi.currency'),
                'method' => $method,
                'status' => $booking->payment_status === PaymentStatus::Paid ? PaymentStatus::Paid : PaymentStatus::Partial,
                'note' => $note,
                'recorded_by' => $actor->id,
                'paid_at' => now(),
            ]);
        });

        $payment->load(['booking', 'customer.user']);
        $this->notifier->toCustomer($payment->customer, 'booking_updated', 'Paiement enregistré',
            "{$payment->amount} F CFA pour la réservation {$payment->booking->reference}.", $payment->booking_id);
        $this->notifier->toAdmins('payment_received', 'Paiement enregistré',
            "{$payment->reference} — {$payment->amount} F CFA ({$payment->customer->fullName()})", '/admin/payments?ref='.$payment->reference, 'payment');

        return $payment;
    }
}
