<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\SalonClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends ProController
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', 'today');
        $today = SalonClock::today();
        $weekStart = SalonClock::now()->startOfWeek()->toDateString();
        $query = Booking::query()->where('professional_id', $this->me($request)->id)
            ->with(['customer', 'professional', 'histories']);
        $notCancelled = fn (Builder $q) => $q->where('status', '!=', BookingStatus::Cancelled->value);
        $between = fn (Builder $q, string $from, string $to) => $q->whereBetween('starts_at', SalonClock::dayBounds($from, $to));

        match ($range) {
            'today' => $between($notCancelled($query), $today, $today),
            'tomorrow' => $between($notCancelled($query), SalonClock::addDays($today, 1), SalonClock::addDays($today, 1)),
            'week' => $between($notCancelled($query), $weekStart, SalonClock::addDays($weekStart, 6)),
            'upcoming' => $query->where('starts_at', '>=', SalonClock::dayBounds($today)[0])
                ->whereIn('status', BookingStatus::values(BookingStatus::active())),
            'completed' => $query->where('status', BookingStatus::Completed->value),
            'cancelled' => $query->whereIn('status', [BookingStatus::Cancelled->value, BookingStatus::NoShow->value]),
            default => throw ApiException::unprocessable('Filtre invalide.', ['range' => 'Filtre invalide.']),
        };
        $history = in_array($range, ['completed', 'cancelled'], true);
        $list = $query->orderBy('starts_at', $history ? 'desc' : 'asc')->limit($history ? 60 : 300)->get();

        return response()->json(BookingResource::collection($list));
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        return response()->json(new BookingResource($this->own($request, $booking)));
    }

    /** `POST /pro/bookings/:id/{confirm|decline|start|complete|no-show}`. */
    public function action(Request $request, string $booking, string $action): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action = $action === 'no-show' ? 'no_show' : $action;
        $model = $this->bookings->actionForProfessional($this->own($request, $booking), $request->user(), $action, $data['reason'] ?? null);

        return response()->json(new BookingResource($model->load('histories')));
    }

    private function own(Request $request, string $id): Booking
    {
        return Booking::query()->whereKey($id)->where('professional_id', $this->me($request)->id)
            ->with(['customer', 'professional', 'histories'])->first()
            ?? throw ApiException::notFound('Réservation introuvable.');
    }
}
