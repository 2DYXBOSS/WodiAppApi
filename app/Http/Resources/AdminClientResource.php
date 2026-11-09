<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Support\SalonClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `AdminClient`. Attend les agrégats `reservations_count` et `last_visit_at` (withCount / withMax).
 *
 * @mixin Customer
 */
class AdminClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone ?? '',
            'email' => $this->email,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'loyaltyTier' => $this->loyalty_tier,
            'vipSince' => $this->vip_since?->toDateString(),
            'preferences' => $this->preferences,
            'notes' => $this->notes,
            'reservationsCount' => (int) ($this->reservations_count ?? 0),
            'lastVisit' => $this->last_visit_at ? SalonClock::localDate(CarbonImmutable::parse($this->last_visit_at)) : null,
            'hasAccount' => $this->user_id !== null,
        ];
    }
}
