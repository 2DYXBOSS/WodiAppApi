<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\AvailabilityRequest;
use App\Models\Booking;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\SettingsService;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;

/** Disponibilités réellement réservables (session requise). */
class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability, private readonly SettingsService $settings) {}

    public function days(AvailabilityRequest $request): JsonResponse
    {
        [$service, $staffId, $exclude] = $this->params($request);
        $from = $request->input('from');
        $to = $request->input('to');
        if (SalonClock::daysBetween($from, $to) > config('wodi.booking.max_days_range')) {
            throw ApiException::unprocessable('Période invalide (62 jours maximum).', ['to' => 'Période trop longue.']);
        }

        return response()->json($this->availability->days($service, $staffId, $from, $to, $exclude));
    }

    public function slots(AvailabilityRequest $request): JsonResponse
    {
        [$service, $staffId, $exclude] = $this->params($request);

        return response()->json($this->availability->slots($service, $staffId, $request->input('date'), $exclude));
    }

    private function params(AvailabilityRequest $request): array
    {
        $service = Service::query()->find($request->input('serviceId')) ?? throw ApiException::notFound('Prestation introuvable.');
        $staffId = $request->input('staffId');
        if ($staffId === null || $staffId === 'any') {
            if (! $this->settings->booking()['allowAnyStaff']) {
                throw ApiException::unprocessable('Veuillez choisir un professionnel.', ['staffId' => 'Professionnel requis.']);
            }
            $staffId = null;
        }
        // `excludeBookingId` (modification) n'est accepté que pour une réservation du client connecté.
        $exclude = $request->input('excludeBookingId');
        if ($exclude) {
            $customer = $request->user()->customer;
            $owned = $customer && Booking::query()->whereKey($exclude)->where('customer_id', $customer->id)->exists();
            $exclude = $owned ? $exclude : null;
        }

        return [$service, $staffId, $exclude];
    }
}
