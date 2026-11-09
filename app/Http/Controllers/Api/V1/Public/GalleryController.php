<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryImageResource;
use App\Models\GalleryImage;
use Illuminate\Http\JsonResponse;

class GalleryController extends Controller
{
    public function index(): JsonResponse
    {
        $images = GalleryImage::query()->where('is_published', true)->orderBy('sort_order')->latest()->get();

        return response()->json(GalleryImageResource::collection($images));
    }
}
