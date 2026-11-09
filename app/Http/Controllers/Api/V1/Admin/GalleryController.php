<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryRequest;
use App\Http\Resources\GalleryImageResource;
use App\Models\GalleryImage;
use App\Services\ActivityLogger;
use App\Services\MediaService;
use App\Services\VitrineRebuild;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GalleryController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger, private readonly VitrineRebuild $vitrine, private readonly MediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $q = GalleryImage::query();
        if ($category = $request->query('category')) {
            $q->where('category', $category);
        }
        if (($published = Page::bool($request, 'published')) !== null) {
            $q->where('is_published', $published);
        }
        Page::search($q, $request->query('search'), ['title', 'description']);

        return response()->json(Page::make($q, $request, fn ($g) => GalleryImageResource::admin($g)->resolve($request),
            ['title' => 'title', 'createdAt' => 'created_at', 'order' => 'sort_order'], fn ($q) => $q->orderBy('sort_order')->orderByDesc('created_at')));
    }

    public function show(string $image): JsonResponse
    {
        return response()->json(GalleryImageResource::admin(GalleryImage::query()->findOrFail($image)));
    }

    public function store(GalleryRequest $request): JsonResponse
    {
        $image = GalleryImage::query()->create($this->attributes($request->validated()));
        $this->changed('gallery.created', $image);

        return response()->json(GalleryImageResource::admin($image), 201);
    }

    public function update(GalleryRequest $request, string $image): JsonResponse
    {
        $model = GalleryImage::query()->findOrFail($image);
        $d = $request->validated();
        $old = $model->image_path;
        $model->update($this->attributes($d));
        if (isset($d['url']) && $d['url'] !== $old) {
            $this->media->deleteIfManaged($old);
        }
        $this->changed('gallery.updated', $model);

        return response()->json(GalleryImageResource::admin($model));
    }

    public function destroy(string $image): Response
    {
        $model = GalleryImage::query()->findOrFail($image);
        $model->delete();
        $this->media->deleteIfManaged($model->image_path);
        $this->changed('gallery.deleted', $model);

        return response()->noContent();
    }

    public function toggle(string $image): JsonResponse
    {
        $model = GalleryImage::query()->findOrFail($image);
        $model->update(['is_published' => ! $model->is_published]);
        $this->changed('gallery.toggled', $model);

        return response()->json(GalleryImageResource::admin($model));
    }

    private function attributes(array $d): array
    {
        $map = ['title' => 'title', 'description' => 'description', 'category' => 'category', 'url' => 'image_path',
            'published' => 'is_published', 'alt' => 'alt', 'orientation' => 'orientation', 'order' => 'sort_order', 'isIllustration' => 'is_illustration'];

        return collect($map)->filter(fn ($c, $in) => array_key_exists($in, $d))->mapWithKeys(fn ($c, $in) => [$c => $d[$in]])->all();
    }

    private function changed(string $action, GalleryImage $image): void
    {
        $this->logger->log($action, $image);
        $this->vitrine->request();
    }
}
