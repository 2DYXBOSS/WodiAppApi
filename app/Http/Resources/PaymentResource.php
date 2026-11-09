<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** `Payment` (mobile, lecture seule). @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'bookingId' => $this->booking_id,
            'bookingReference' => $this->booking?->reference ?? '',
            'serviceName' => $this->booking?->service_name_snapshot ?? 'Prestation',
            'amount' => $this->amount,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'date' => ($this->paid_at ?? $this->created_at)->toIso8601ZuluString(),
        ];
    }
}
