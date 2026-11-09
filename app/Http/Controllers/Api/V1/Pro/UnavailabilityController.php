<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Exceptions\ApiException;
use App\Http\Requests\Pro\StoreUnavailabilityRequest;
use App\Models\Unavailability;
use App\Services\BookingService;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Indisponibilités ponctuelles du professionnel (prises en compte immédiatement dans les créneaux). */
class UnavailabilityController extends ProController
{
    public static function present(Unavailability $u): array
    {
        return [
            'id' => $u->id,
            'date' => $u->date->toDateString(),
            'start' => SalonClock::hm($u->start_time),
            'end' => SalonClock::hm($u->end_time),
            'reason' => $u->reason,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $list = $this->me($request)->unavailabilities()->where('date', '>=', SalonClock::today())
            ->orderBy('date')->orderBy('start_time')->get();

        return response()->json($list->map(fn ($u) => self::present($u)));
    }

    public function store(StoreUnavailabilityRequest $request, BookingService $bookings): JsonResponse
    {
        $data = $request->validated();
        $pro = $this->me($request);
        $start = SalonClock::minutes($data['start']);
        $end = SalonClock::minutes($data['end']);
        if ($start >= $end) {
            throw ApiException::unprocessable('L’heure de fin doit suivre l’heure de début.', ['end' => 'Heure de fin invalide.']);
        }
        $conflict = $bookings->activeBookingsBetween($pro->id, $data['date'], $data['date'])->first(function ($b) use ($start, $end) {
            $bs = SalonClock::minutes(SalonClock::localTime($b->starts_at));

            return $start < $bs + $b->duration_minutes && $bs < $end;
        });
        if ($conflict) {
            throw ApiException::conflict("Une réservation existe sur ce créneau ({$conflict->reference} à ".SalonClock::localTime($conflict->starts_at).'). Contactez le salon pour la déplacer.');
        }
        $duplicate = $pro->unavailabilities()->whereDate('date', $data['date'])
            ->where('start_time', '<', $data['end'])->where('end_time', '>', $data['start'])->exists();
        if ($duplicate) {
            throw ApiException::conflict('Une indisponibilité existe déjà sur ce créneau.');
        }
        $u = $pro->unavailabilities()->create([
            'date' => $data['date'], 'start_time' => $data['start'], 'end_time' => $data['end'],
            'reason' => $data['reason'] ?? null, 'created_by' => $request->user()->id,
        ]);

        return response()->json(self::present($u), 201);
    }

    public function destroy(Request $request, string $unavailability): Response
    {
        $u = $this->me($request)->unavailabilities()->whereKey($unavailability)->first() ?? throw ApiException::notFound();
        if ($u->date->toDateString() < SalonClock::today()) {
            throw ApiException::conflict('Impossible de supprimer une indisponibilité passée.');
        }
        $u->delete();

        return response()->noContent();
    }
}
