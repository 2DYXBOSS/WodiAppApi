<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Http\Resources\LoyaltyEventResource;
use App\Http\Resources\PromotionResource;
use App\Models\LoyaltyTransaction;
use App\Models\Promotion;
use App\Services\ActivityLogger;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): JsonResponse
    {
        $q = Promotion::query();
        if (($active = Page::bool($request, 'active')) !== null) {
            $q->where('is_active', $active);
        }
        Page::search($q, $request->query('search'), ['name']);

        return response()->json(Page::make($q, $request, fn ($p) => (new PromotionResource($p))->resolve($request),
            ['name' => 'name', 'startDate' => 'start_date', 'endDate' => 'end_date', 'createdAt' => 'created_at'], fn ($q) => $q->orderByDesc('start_date')));
    }

    public function show(string $promotion): JsonResponse
    {
        return response()->json(new PromotionResource(Promotion::query()->findOrFail($promotion)));
    }

    public function store(PromotionRequest $request): JsonResponse
    {
        $promotion = Promotion::query()->create($this->attributes($request->validated()));
        $this->logger->log('promotion.created', $promotion);

        return response()->json(new PromotionResource($promotion), 201);
    }

    public function update(PromotionRequest $request, string $promotion): JsonResponse
    {
        $model = Promotion::query()->findOrFail($promotion);
        $model->update($this->attributes($request->validated()));
        $this->logger->log('promotion.updated', $model);

        return response()->json(new PromotionResource($model));
    }

    public function destroy(string $promotion): Response
    {
        $model = Promotion::query()->findOrFail($promotion);
        $model->delete();
        $this->logger->log('promotion.deleted', $model);

        return response()->noContent();
    }

    public function toggle(string $promotion): JsonResponse
    {
        $model = Promotion::query()->findOrFail($promotion);
        $model->update(['is_active' => ! $model->is_active]);

        return response()->json(new PromotionResource($model));
    }

    /** `GET /admin/loyalty/events?clientId=` */
    public function loyaltyEvents(Request $request): JsonResponse
    {
        $q = LoyaltyTransaction::query()->with('customer');
        if ($client = $request->query('clientId')) {
            $q->where('customer_id', $client);
        }

        return response()->json(Page::make($q, $request, fn ($e) => (new LoyaltyEventResource($e))->resolve($request),
            ['date' => 'occurred_on'], fn ($q) => $q->orderByDesc('occurred_on')->orderByDesc('created_at')));
    }

    private function attributes(array $d): array
    {
        $map = ['name' => 'name', 'description' => 'description', 'discountType' => 'discount_type', 'value' => 'value',
            'startDate' => 'start_date', 'endDate' => 'end_date', 'conditions' => 'conditions', 'active' => 'is_active'];

        return collect($map)->filter(fn ($c, $in) => array_key_exists($in, $d))->mapWithKeys(fn ($c, $in) => [$c => $d[$in]])->all();
    }
}
