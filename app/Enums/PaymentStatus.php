<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Partial = 'partial';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** Statut de paiement d'une réservation selon le montant encaissé. */
    public static function forAmounts(?int $due, int $paid): self
    {
        if ($paid <= 0) {
            return self::Pending;
        }

        return $due !== null && $paid >= $due ? self::Paid : self::Partial;
    }
}
