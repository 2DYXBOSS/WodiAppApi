<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\SettingsService;
use App\Support\Media;
use App\Support\SalonClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `Booking` du mobile, filtré selon l'utilisateur courant (client ou professionnel).
 * Nécessite `customer`, `professional`, `histories` préchargés.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $bookings = app(BookingService::class);
        $isPro = $viewer?->isBarber() ?? false;
        $deposit = (int) app(SettingsService::class)->booking()['depositPercent'];
        $start = SalonClock::local($this->starts_at);
        $activeForPhone = in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::InProgress], true);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'endTime' => SalonClock::localTime($this->ends_at),
            'durationMinutes' => $this->duration_minutes,
            'location' => $this->location,
            'notes' => $this->customer_note,
            'service' => ['id' => $this->service_id, 'name' => $this->service_name_snapshot],
            'staff' => [
                'id' => $this->professional_id,
                'name' => $this->professional?->fullName() ?? 'Professionnel',
                'photo' => Media::url($this->professional?->photo),
            ],
            // Informations client nécessaires au professionnel uniquement (téléphone tant que le RDV est actif).
            'client' => $isPro && $this->customer ? [
                'id' => $this->customer->id,
                'firstName' => $this->customer->first_name,
                'lastName' => $this->customer->last_name,
                'phone' => $activeForPhone ? $this->customer->phone : null,
                'preferences' => $this->customer->preferences,
            ] : null,
            'amount' => $this->price_snapshot,
            'amountPaid' => $this->amount_paid,
            'paymentStatus' => $this->payment_status->value,
            'depositAmount' => $deposit > 0 && $this->price_snapshot ? (int) round($this->price_snapshot * $deposit / 100) : null,
            'allowedActions' => $viewer ? $bookings->allowedActions($this->resource, $viewer) : [],
            'cancelBlockedReason' => $viewer?->isClient() ? $bookings->clientCancelBlockedReason($this->resource) : null,
            'cancellationReason' => $this->cancellation_reason,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'history' => HistoryEntryResource::collection($this->histories)->resolve(),
        ];
    }
}
