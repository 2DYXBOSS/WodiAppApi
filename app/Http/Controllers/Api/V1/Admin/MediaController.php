<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaRequest;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

/** `POST /admin/media` (multipart `file`) → `{ url }`. JPEG/PNG/WebP, 5 Mo max, type contrôlé côté serveur. */
class MediaController extends Controller
{
    public function store(MediaRequest $request, MediaService $media): JsonResponse
    {
        if (! $request->user()->hasAnyPermission(['gallery.manage', 'content.manage', 'services.manage', 'team.manage'])) {
            throw ApiException::forbidden();
        }

        return response()->json(['url' => $media->store($request->file('file'), $request->input('folder', 'uploads'))], 201);
    }
}
