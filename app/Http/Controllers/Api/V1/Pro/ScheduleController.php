<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Http\Requests\Pro\SaveScheduleRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\SalonClosure;
use App\Services\ScheduleService;
use App\Services\SettingsService;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends ProController
{
    public function __construct(private readonly ScheduleService $schedules, private readonly SettingsService $settings) {}

    public function agenda(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        if (SalonClock::daysBetween($data['from'], $data['to']) > config('wodi.booking.max_days_range')) {
            throw ApiException::unprocessable('Période invalide.', ['to' => 'Période trop longue.']);
        }
        $pro = $this->me($request);

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'schedule' => $this->weekly($pro),
            'salonHours' => $this->settings->openingHours(),
            'closedDates' => SalonClosure::query()->where('is_closed', true)->whereBetween('date', [$data['from'], $data['to']])
                ->orderBy('date')->pluck('date')->map(fn ($d) => $d->toDateString())->all(),
            'bookings' => BookingResource::collection(Booking::query()->where('professional_id', $pro->id)
                ->whereBetween('starts_at', SalonClock::dayBounds($data['from'], $data['to']))
                ->where('status', '!=', BookingStatus::Cancelled->value)
                ->with(['customer', 'professional', 'histories'])->orderBy('starts_at')->get())->resolve($request),
            'unavailabilities' => $pro->unavailabilities()->whereBetween('date', [$data['from'], $data['to']])
                ->orderBy('date')->orderBy('start_time')->get()->map(fn ($u) => UnavailabilityController::present($u))->all(),
            'absences' => $pro->absences()->where('end_date', '>=', $data['from'])->where('start_date', '<=', $data['to'])
                ->orderBy('start_date')->get()->map(fn ($a) => AbsenceController::present($a))->all(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->weekly($this->me($request)));
    }

    public function update(SaveScheduleRequest $request): JsonResponse
    {
        $pro = $this->me($request);
        $this->schedules->save($pro, $request->validated('days'));

        return response()->json($this->weekly($pro));
    }

    private function weekly($pro): array
    {
        return ['staffId' => $pro->id, 'days' => $this->schedules->days($pro), 'updatedAt' => $this->schedules->updatedAt($pro)];
    }
}
