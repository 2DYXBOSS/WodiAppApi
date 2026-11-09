<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationRequest;
use App\Http\Resources\AdminReservationResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\Page;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $q = Booking::query()->with(['customer', 'professional', 'histories']);
        foreach (['status' => 'status', 'paymentStatus' => 'payment_status', 'staffId' => 'professional_id',
            'serviceId' => 'service_id', 'clientId' => 'customer_id', 'reference' => 'reference'] as $param => $column) {
            if ($value = $request->query($param)) {
                $q->where($column, $value);
            }
        }
        if ($date = $request->query('date')) {
            $q->whereBetween('starts_at', SalonClock::dayBounds($date));
        }
        if ($from = $request->query('dateFrom')) {
            $q->where('starts_at', '>=', SalonClock::dayBounds($from)[0]);
        }
        if ($to = $request->query('dateTo')) {
            $q->where('starts_at', '<', SalonClock::dayBounds($to)[1]);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('reference', 'ilike', "%{$search}%")->orWhere('service_name_snapshot', 'ilike', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => Page::search($c, $search, ['first_name', 'last_name', 'phone'])));
        }

        return response()->json(Page::make($q, $request, fn ($b) => (new AdminReservationResource($b))->resolve($request), [
            'date' => 'starts_at', 'createdAt' => 'created_at', 'amount' => 'price_snapshot', 'status' => 'status', 'reference' => 'reference',
        ], fn ($q) => $q->orderByDesc('starts_at')));
    }

    public function show(string $reservation): JsonResponse
    {
        return response()->json(new AdminReservationResource($this->find($reservation)));
    }

    public function store(ReservationRequest $request): JsonResponse
    {
        $booking = $this->bookings->createForAdmin($request->user(), $request->validated());

        return response()->json(new AdminReservationResource($booking->load('histories')), 201);
    }

    public function update(ReservationRequest $request, string $reservation): JsonResponse
    {
        $booking = $this->bookings->updateForAdmin($this->find($reservation), $request->user(), $request->validated());

        return response()->json(new AdminReservationResource($booking->load(['customer', 'professional', 'histories'])));
    }

    /** Une réservation ne se supprime jamais (historique) : on l'annule. */
    public function destroy(): never
    {
        throw ApiException::conflict('Une réservation ne se supprime pas : annulez-la pour conserver l’historique.');
    }

    /** `POST /admin/reservations/:id/{confirm|start|complete|no-show|cancel}`. */
    public function action(Request $request, string $reservation, string $action): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action = $action === 'no-show' ? 'no_show' : $action;
        $booking = $this->bookings->actionForAdmin($this->find($reservation), $request->user(), $action, $data['reason'] ?? null);

        return response()->json(new AdminReservationResource($booking->load('histories')));
    }

    private function find(string $id): Booking
    {
        return Booking::query()->with(['customer', 'professional', 'histories'])->findOrFail($id);
    }
}
