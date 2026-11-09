<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $data = $request->validate(['period' => ['nullable', 'in:today,week,month,year']]);

        return response()->json($dashboard->build($data['period'] ?? 'today', $request));
    }
}
