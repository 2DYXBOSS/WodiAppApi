<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\ApprovalStatus;
use App\Exceptions\ApiException;
use App\Http\Requests\Pro\StoreAbsenceRequest;
use App\Models\Absence;
use App\Services\BookingService;
use App\Services\Notifier;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Demandes d'absence/congé : statut `pending` jusqu'à validation par le salon. */
class AbsenceController extends ProController
{
    public static function present(Absence $a): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type->value,
            'startDate' => $a->start_date->toDateString(),
            'endDate' => $a->end_date->toDateString(),
            'reason' => $a->reason,
            'status' => $a->status->value,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $list = $this->me($request)->absences()->where('end_date', '>=', SalonClock::addDays(SalonClock::today(), -60))
            ->orderByDesc('start_date')->get();

        return response()->json($list->map(fn ($a) => self::present($a)));
    }

    public function store(StoreAbsenceRequest $request, BookingService $bookings, Notifier $notifier): JsonResponse
    {
        $data = $request->validated();
        $pro = $this->me($request);
        $conflicts = $bookings->activeBookingsBetween($pro->id, $data['startDate'], $data['endDate']);
        if ($conflicts->isNotEmpty()) {
            throw ApiException::conflict("{$conflicts->count()} réservation(s) existent sur cette période (ex. {$conflicts->first()->reference}). Contactez le salon pour les réattribuer.");
        }
        $overlap = $pro->absences()->where('status', '!=', ApprovalStatus::Rejected->value)
            ->where('start_date', '<=', $data['endDate'])->where('end_date', '>=', $data['startDate'])->exists();
        if ($overlap) {
            throw ApiException::conflict('Une absence existe déjà sur cette période.');
        }
        $absence = $pro->absences()->create([
            'type' => $data['type'], 'start_date' => $data['startDate'], 'end_date' => $data['endDate'],
            'reason' => $data['reason'] ?? null, 'status' => ApprovalStatus::Pending, 'created_by' => $request->user()->id,
        ]);
        $notifier->toAdmins('absence_request', 'Demande d’absence',
            "{$pro->fullName()} : {$absence->type->label()} du {$data['startDate']} au {$data['endDate']}", '/admin/unavailabilities');

        return response()->json(self::present($absence), 201);
    }

    public function destroy(Request $request, string $absence): Response
    {
        $a = $this->me($request)->absences()->whereKey($absence)->first() ?? throw ApiException::notFound();
        if ($a->start_date->toDateString() <= SalonClock::today()) {
            throw ApiException::conflict('Une absence commencée ne peut plus être retirée. Contactez le salon.');
        }
        $a->delete();

        return response()->noContent();
    }
}
