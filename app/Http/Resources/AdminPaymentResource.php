<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** `AdminPayment`. @mixin \App\Models\Payment */
class AdminPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'reservationId' => $this->booking_id,
            'reservationRef' => $this->booking?->reference ?? '',
            'clientId' => $this->customer_id,
            'clientName' => $this->customer?->fullName() ?? '',
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'date' => ($this->paid_at ?? $this->created_at)->toIso8601ZuluString(),
            'note' => $this->note,
        ];
    }
}
