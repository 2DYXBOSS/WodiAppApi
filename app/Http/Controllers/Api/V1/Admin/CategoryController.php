<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ActivityLogger;
use App\Services\VitrineRebuild;
use App\Support\Page;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger, private readonly VitrineRebuild $vitrine) {}

    public function index(Request $request): JsonResponse
    {
        $q = ServiceCategory::query()->withCount('services');
        Page::search($q, $request->query('search'), ['name']);

        return response()->json(Page::make($q, $request, fn ($c) => (new AdminCategoryResource($c))->resolve($request),
            ['name' => 'name', 'order' => 'sort_order', 'createdAt' => 'created_at'], fn ($q) => $q->orderBy('sort_order')->orderBy('name')));
    }

    public function show(string $category): JsonResponse
    {
        return response()->json(new AdminCategoryResource(ServiceCategory::query()->withCount('services')->findOrFail($category)));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $d = $request->validated();
        $category = ServiceCategory::query()->create($this->attributes($d) + [
            'slug' => Slug::unique(ServiceCategory::class, $d['name']),
            'sort_order' => $d['order'] ?? (int) ServiceCategory::query()->max('sort_order') + 1,
        ]);
        $this->changed('category.created', $category);

        return response()->json(new AdminCategoryResource($category->loadCount('services')), 201);
    }

    public function update(CategoryRequest $request, string $category): JsonResponse
    {
        $model = ServiceCategory::query()->findOrFail($category);
        $model->update($this->attributes($request->validated()));
        $this->changed('category.updated', $model);

        return response()->json(new AdminCategoryResource($model->loadCount('services')));
    }

    public function destroy(string $category): Response
    {
        $model = ServiceCategory::query()->findOrFail($category);
        if (Service::withTrashed()->where('category_id', $model->id)->exists()) {
            throw ApiException::conflict('Cette catégorie contient des prestations : désactivez-la plutôt que de la supprimer.');
        }
        $model->delete();
        $this->changed('category.deleted', $model);

        return response()->noContent();
    }

    private function attributes(array $d): array
    {
        $map = ['name' => 'name', 'description' => 'description', 'order' => 'sort_order', 'tagline' => 'tagline',
            'icon' => 'icon', 'image' => 'image', 'imageAlt' => 'image_alt', 'active' => 'is_active'];

        return collect($map)->filter(fn ($col, $in) => array_key_exists($in, $d))->mapWithKeys(fn ($col, $in) => [$col => $d[$in]])->all();
    }

    private function changed(string $action, ServiceCategory $category): void
    {
        $this->logger->log($action, $category);
        $this->vitrine->request();
    }
}
