<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Prestations du professionnel : activation possible avec `services.manage_own`, tarifs jamais modifiables. */
class ServiceController extends ProController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function toggle(Request $request, string $service): JsonResponse
    {
        if (! $request->user()->can('services.manage_own')) {
            throw ApiException::forbidden();
        }
        $pro = $this->me($request);
        $model = $pro->services()->whereKey($service)->first() ?? throw ApiException::notFound();
        if (! $model->is_active) {
            throw ApiException::conflict('Prestation désactivée par le salon.');
        }
        $enabled = (bool) $model->pivot->is_active;
        if ($enabled) {
            $upcoming = $pro->bookings()->where('service_id', $model->id)->where('starts_at', '>=', now())
                ->whereIn('status', BookingStatus::values(BookingStatus::active()))->exists();
            if ($upcoming) {
                throw ApiException::conflict('Des réservations à venir concernent cette prestation : elle ne peut pas être retirée maintenant.');
            }
        }
        $pro->services()->updateExistingPivot($model->id, ['is_active' => ! $enabled]);

        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $services = $this->me($request)->services()->with('category')->orderBy('sort_order')->orderBy('name')->get();

        return [
            'canManage' => $request->user()->can('services.manage_own'),
            'services' => $services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description ?? '',
                'categoryName' => $s->category?->name ?? '',
                'durationMinutes' => $s->duration_minutes,
                'price' => $s->price,
                'active' => $s->is_active && (bool) $s->pivot->is_active,
            ])->values()->all(),
        ];
    }
}
