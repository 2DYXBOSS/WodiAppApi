<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamMemberResource;
use App\Models\Professional;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/** Équipe publique : professionnels actifs et visibles, sans aucune donnée privée. */
class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TeamMemberResource::collection($this->query()->get()));
    }

    public function show(string $professional): JsonResponse
    {
        return response()->json(new TeamMemberResource($this->query()->whereKey($professional)->first() ?? abort(404)));
    }

    private function query(): Builder
    {
        return Professional::query()->active()->where('is_public', true)
            ->with(['workingHours', 'services' => fn ($q) => $q->where('services.is_active', true)->wherePivot('is_active', true)])
            ->orderBy('sort_order')->orderBy('first_name');
    }
}
