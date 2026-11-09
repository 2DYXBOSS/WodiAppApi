<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ProController
{
    public function dashboard(Request $request): JsonResponse
    {
        $pro = $this->me($request);
        $today = SalonClock::today();
        $todays = Booking::query()->where('professional_id', $pro->id)
            ->whereBetween('starts_at', SalonClock::dayBounds($today))
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->with(['customer', 'professional', 'histories'])->orderBy('starts_at')->get();
        $active = BookingStatus::values(BookingStatus::active());
        $next = Booking::query()->where('professional_id', $pro->id)->whereIn('status', $active)
            ->where('starts_at', '>=', now())->with(['customer', 'professional', 'histories'])->orderBy('starts_at')->first();

        return response()->json([
            'today' => [
                'total' => $todays->count(),
                'upcoming' => $todays->filter(fn ($b) => in_array($b->status->value, $active, true))->count(),
                'completed' => $todays->where('status', BookingStatus::Completed)->count(),
                'pending' => Booking::query()->where('professional_id', $pro->id)->where('status', BookingStatus::Pending->value)
                    ->where('starts_at', '>=', SalonClock::dayBounds($today)[0])->count(),
            ],
            'todayBookings' => BookingResource::collection($todays)->resolve($request),
            'nextBooking' => $next ? (new BookingResource($next))->resolve($request) : null,
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', 'month');
        $days = ['week' => 7, 'month' => 30, 'year' => 365][$period] ?? throw ApiException::unprocessable('Période invalide.', ['period' => 'Période invalide.']);
        $pro = $this->me($request);
        $today = SalonClock::today();
        [$from] = SalonClock::dayBounds(SalonClock::addDays($today, -$days + 1));
        [, $to] = SalonClock::dayBounds($today);

        $inPeriod = Booking::query()->where('professional_id', $pro->id)->whereBetween('starts_at', [$from, $to]);
        $completed = (clone $inPeriod)->where('status', BookingStatus::Completed->value)
            ->with(['customer', 'professional', 'histories'])->orderByDesc('starts_at')->get();

        return response()->json([
            'period' => $period,
            'completed' => $completed->count(),
            'upcoming' => Booking::query()->where('professional_id', $pro->id)->where('starts_at', '>=', now())
                ->whereIn('status', BookingStatus::values(BookingStatus::active()))->count(),
            'cancelled' => (clone $inPeriod)->where('status', BookingStatus::Cancelled->value)->count(),
            'noShow' => (clone $inPeriod)->where('status', BookingStatus::NoShow->value)->count(),
            'topServices' => $completed->groupBy('service_name_snapshot')
                ->map(fn ($g, $name) => ['serviceName' => $name, 'count' => $g->count()])
                ->sortByDesc('count')->take(5)->values()->all(),
            'revenue' => $request->user()->can('activity.revenue.view') ? (int) $completed->sum('price_snapshot') : null,
            'recent' => BookingResource::collection($completed->take(8))->resolve($request),
        ]);
    }
}
