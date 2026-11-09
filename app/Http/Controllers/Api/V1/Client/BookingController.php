<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\CreateBookingRequest;
use App\Http\Requests\Me\RescheduleBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Réservations du client connecté UNIQUEMENT (404 pour celles d'autrui). */
class BookingController extends Controller
{
    private const SCOPES = [
        'upcoming' => [BookingStatus::Pending, BookingStatus::Confirmed],
        'in_progress' => [BookingStatus::InProgress],
        'past' => [BookingStatus::Completed, BookingStatus::NoShow],
        'cancelled' => [BookingStatus::Cancelled],
    ];

    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $scope = (string) $request->query('scope', 'upcoming');
        $statuses = self::SCOPES[$scope] ?? throw ApiException::unprocessable('Filtre invalide.', ['scope' => 'Filtre invalide.']);
        $ascending = in_array($scope, ['upcoming', 'in_progress'], true);

        $list = Booking::query()->where('customer_id', $request->user()->customer->id)
            ->whereIn('status', BookingStatus::values($statuses))
            ->with(['professional', 'histories'])
            ->orderBy('starts_at', $ascending ? 'asc' : 'desc')
            ->limit(200)->get();

        return response()->json(BookingResource::collection($list));
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        return response()->json(new BookingResource($this->own($request, $booking)));
    }

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->createForClient($request->user()->customer, $request->user(), $request->validated());

        return response()->json(new BookingResource($booking->load('histories')), 201);
    }

    public function cancel(Request $request, string $booking): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $model = $this->bookings->cancelForClient($this->own($request, $booking), $request->user(), $data['reason'] ?? null);

        return response()->json(new BookingResource($model->load('histories')));
    }

    public function reschedule(RescheduleBookingRequest $request, string $booking): JsonResponse
    {
        $model = $this->bookings->rescheduleForClient($this->own($request, $booking), $request->user(), $request->validated());

        return response()->json(new BookingResource($model->load(['professional', 'histories'])));
    }

    private function own(Request $request, string $id): Booking
    {
        return Booking::query()->whereKey($id)->where('customer_id', $request->user()->customer->id)
            ->with(['customer', 'professional', 'histories'])->first()
            ?? throw ApiException::notFound('Réservation introuvable.');
    }
}
