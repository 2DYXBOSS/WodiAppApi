<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApprovalStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUnavailabilityRequest;
use App\Http\Resources\AdminUnavailabilityResource;
use App\Models\Absence;
use App\Services\ActivityLogger;
use App\Services\BookingService;
use App\Services\Notifier;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Congés / absences / indisponibilités exceptionnelles (journées entières) + validation des demandes. */
class UnavailabilityController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger, private readonly BookingService $bookings, private readonly Notifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $q = Absence::query()->with('professional');
        foreach (['staffId' => 'professional_id', 'type' => 'type', 'status' => 'status'] as $param => $col) {
            if ($v = $request->query($param)) {
                $q->where($col, $v);
            }
        }
        if ($from = $request->query('from')) {
            $q->where('end_date', '>=', $from);
        }

        return response()->json(Page::make($q, $request, fn ($a) => (new AdminUnavailabilityResource($a))->resolve($request),
            ['startDate' => 'start_date', 'createdAt' => 'created_at'], fn ($q) => $q->orderBy('start_date')));
    }

    public function show(string $unavailability): JsonResponse
    {
        return response()->json(new AdminUnavailabilityResource(Absence::query()->with('professional')->findOrFail($unavailability)));
    }

    /** Créée par le salon : validée d'office. 409 si des réservations actives existent sur la période. */
    public function store(AdminUnavailabilityRequest $request): JsonResponse
    {
        $d = $request->validated();
        $this->assertNoBookings($d['staffId'], $d['startDate'], $d['endDate']);
        $absence = Absence::query()->create([
            'professional_id' => $d['staffId'], 'type' => $d['type'], 'start_date' => $d['startDate'], 'end_date' => $d['endDate'],
            'reason' => $d['reason'] ?? null, 'status' => ApprovalStatus::Approved, 'created_by' => $request->user()->id,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);
        $this->logger->log('absence.created', $absence);

        return response()->json(new AdminUnavailabilityResource($absence->load('professional')), 201);
    }

    public function update(AdminUnavailabilityRequest $request, string $unavailability): JsonResponse
    {
        $absence = Absence::query()->findOrFail($unavailability);
        $d = $request->validated();
        $staffId = $d['staffId'] ?? $absence->professional_id;
        $start = $d['startDate'] ?? $absence->start_date->toDateString();
        $end = $d['endDate'] ?? $absence->end_date->toDateString();
        $this->assertNoBookings($staffId, $start, $end);
        $absence->update(array_filter([
            'professional_id' => $staffId, 'type' => $d['type'] ?? null, 'start_date' => $start, 'end_date' => $end,
        ]) + (array_key_exists('reason', $d) ? ['reason' => $d['reason']] : []));
        $this->logger->log('absence.updated', $absence);

        return response()->json(new AdminUnavailabilityResource($absence->load('professional')));
    }

    public function destroy(string $unavailability): Response
    {
        $absence = Absence::query()->findOrFail($unavailability);
        $absence->delete();
        $this->logger->log('absence.deleted', $absence);

        return response()->noContent();
    }

    public function approve(Request $request, string $unavailability): JsonResponse
    {
        return $this->review($request, $unavailability, ApprovalStatus::Approved);
    }

    public function reject(Request $request, string $unavailability): JsonResponse
    {
        return $this->review($request, $unavailability, ApprovalStatus::Rejected);
    }

    private function review(Request $request, string $id, ApprovalStatus $status): JsonResponse
    {
        $absence = Absence::query()->with('professional.user')->findOrFail($id);
        if ($absence->status !== ApprovalStatus::Pending) {
            throw ApiException::conflict('Cette demande a déjà été traitée.');
        }
        $absence->update(['status' => $status, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $approved = $status === ApprovalStatus::Approved;
        $this->notifier->toProfessional($absence->professional, $approved ? 'availability_approved' : 'availability_rejected',
            $approved ? 'Absence validée' : 'Absence refusée',
            "{$absence->type->label()} du {$absence->start_date->toDateString()} au {$absence->end_date->toDateString()}");
        $this->logger->log('absence.'.$status->value, $absence);

        return response()->json(new AdminUnavailabilityResource($absence));
    }

    private function assertNoBookings(string $staffId, string $from, string $to): void
    {
        $conflicts = $this->bookings->activeBookingsBetween($staffId, $from, $to);
        if ($conflicts->isNotEmpty()) {
            throw ApiException::conflict("{$conflicts->count()} réservation(s) active(s) sur cette période (ex. {$conflicts->first()->reference}) : déplacez-les ou annulez-les d’abord.");
        }
    }
}
