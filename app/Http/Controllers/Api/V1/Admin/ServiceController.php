<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Http\Resources\AdminServiceResource;
use App\Models\Professional;
use App\Models\Service;
use App\Services\ActivityLogger;
use App\Services\VitrineRebuild;
use App\Support\Page;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger, private readonly VitrineRebuild $vitrine) {}

    public function index(Request $request): JsonResponse
    {
        $q = Service::query()->with(['category', 'professionals']);
        if ($category = $request->query('categoryId')) {
            $q->where('category_id', $category);
        }
        if (($active = Page::bool($request, 'active')) !== null) {
            $q->where('is_active', $active);
        }
        Page::search($q, $request->query('search'), ['name', 'description']);

        return response()->json(Page::make($q, $request, fn ($s) => (new AdminServiceResource($s))->resolve($request), [
            'name' => 'name', 'price' => 'price', 'durationMinutes' => 'duration_minutes', 'createdAt' => 'created_at', 'order' => 'sort_order',
        ], fn ($q) => $q->orderBy('sort_order')->orderBy('name')));
    }

    public function show(string $service): JsonResponse
    {
        return response()->json(new AdminServiceResource(Service::query()->with(['category', 'professionals'])->findOrFail($service)));
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $d = $request->validated();
        $service = DB::transaction(function () use ($d) {
            $service = Service::query()->create($this->attributes($d) + [
                'slug' => Slug::unique(Service::class, $d['name']),
                'sort_order' => $d['order'] ?? (int) Service::query()->max('sort_order') + 1,
            ]);
            // Par défaut, tous les professionnels actifs peuvent réaliser une nouvelle prestation
            // (le back-office actuel n'a pas d'écran d'affectation).
            $staffIds = $d['staffIds'] ?? Professional::query()->active()->pluck('id')->all();
            $service->professionals()->sync($staffIds);

            return $service;
        });
        $this->changed('service.created', $service);

        return response()->json(new AdminServiceResource($service->load(['category', 'professionals'])), 201);
    }

    public function update(ServiceRequest $request, string $service): JsonResponse
    {
        $model = Service::query()->findOrFail($service);
        $d = $request->validated();
        DB::transaction(function () use ($model, $d) {
            $model->update($this->attributes($d));
            if (array_key_exists('staffIds', $d)) {
                // Conserve l'état d'activation choisi par chaque professionnel.
                $model->professionals()->syncWithoutDetaching($d['staffIds']);
                $model->professionals()->detach($model->professionals()->pluck('professionals.id')->diff($d['staffIds'])->all());
            }
        });
        $this->changed('service.updated', $model, array_keys($d));

        return response()->json(new AdminServiceResource($model->load(['category', 'professionals'])));
    }

    public function destroy(string $service): Response
    {
        $model = Service::query()->findOrFail($service);
        if ($model->bookings()->exists()) {
            throw ApiException::conflict('Cette prestation figure dans des réservations : désactivez-la plutôt que de la supprimer.');
        }
        $model->delete();
        $this->changed('service.deleted', $model);

        return response()->noContent();
    }

    public function toggle(string $service): JsonResponse
    {
        $model = Service::query()->findOrFail($service);
        $model->update(['is_active' => ! $model->is_active]);
        $this->changed('service.toggled', $model, ['active' => $model->is_active]);

        return response()->json(new AdminServiceResource($model->load(['category', 'professionals'])));
    }

    private function attributes(array $d): array
    {
        $map = ['name' => 'name', 'categoryId' => 'category_id', 'description' => 'description', 'price' => 'price',
            'priceFrom' => 'price_from', 'durationMinutes' => 'duration_minutes', 'active' => 'is_active', 'image' => 'image',
            'conditions' => 'conditions', 'bookableOnline' => 'is_bookable_online', 'order' => 'sort_order'];
        $attributes = collect($map)->filter(fn ($col, $in) => array_key_exists($in, $d))->mapWithKeys(fn ($col, $in) => [$col => $d[$in]])->all();
        if (isset($d['locations'])) {
            $attributes['available_in_salon'] = in_array('salon', $d['locations'], true);
            $attributes['available_at_home'] = in_array('domicile', $d['locations'], true);
        }

        return $attributes;
    }

    private function changed(string $action, Service $service, array $properties = []): void
    {
        $this->logger->log($action, $service, $properties);
        $this->vitrine->request();
    }
}
