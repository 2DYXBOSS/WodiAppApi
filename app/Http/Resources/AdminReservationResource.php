<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Support\SalonClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `AdminReservation` (back-office). Nécessite `customer`, `professional`, `histories`.
 *
 * @mixin Booking
 */
class AdminReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $start = SalonClock::local($this->starts_at);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'clientId' => $this->customer_id,
            'clientName' => $this->customer?->fullName() ?? '',
            'clientPhone' => $this->customer?->phone ?? '',
            'serviceId' => $this->service_id,
            'serviceName' => $this->service_name_snapshot,
            'staffId' => $this->professional_id,
            'staffName' => $this->professional?->fullName() ?? '',
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'durationMinutes' => $this->duration_minutes,
            'amount' => $this->price_snapshot ?? 0,
            'amountPaid' => $this->amount_paid,
            'status' => $this->status->value,
            'paymentStatus' => $this->payment_status->value,
            'location' => $this->location,
            'notes' => $this->customer_note,
            'cancellationReason' => $this->cancellation_reason,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'history' => HistoryEntryResource::collection($this->histories)->resolve(),
        ];
    }
}
