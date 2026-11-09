<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogCategoryResource;
use App\Http\Resources\CatalogServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/** Catalogue public (vitrine + mobile) : uniquement les éléments actifs, triés par le serveur. */
class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(CatalogCategoryResource::collection($categories));
    }

    public function services(): JsonResponse
    {
        return response()->json(CatalogServiceResource::collection($this->query()->get()));
    }

    public function service(string $service): JsonResponse
    {
        $model = $this->query()->whereKey($service)->first() ?? abort(404);

        return response()->json(new CatalogServiceResource($model));
    }

    private function query(): Builder
    {
        return Service::query()
            ->where('services.is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with(['category', 'professionals' => fn ($q) => $q
                ->where('professionals.is_active', true)
                ->where('professionals.is_bookable', true)
                ->wherePivot('is_active', true)])
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->orderBy('service_categories.sort_order')->orderBy('services.sort_order')->orderBy('services.name')
            ->select('services.*');
    }
}
