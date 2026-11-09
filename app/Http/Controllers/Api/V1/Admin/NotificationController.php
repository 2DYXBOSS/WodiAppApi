<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Notifications du compte administrateur connecté. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->user()->notifications()->getQuery();
        if (($read = Page::bool($request, 'read')) !== null) {
            $read ? $q->whereNotNull('read_at') : $q->whereNull('read_at');
        }
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }

        return response()->json(Page::make($q, $request, fn ($n) => (new NotificationResource($n))->resolve($request),
            ['createdAt' => 'created_at'], fn ($q) => $q->reorder()->orderByDesc('created_at')));
    }

    public function show(Request $request, string $notification): JsonResponse
    {
        return response()->json(new NotificationResource($request->user()->notifications()->findOrFail($notification)));
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $n = $request->user()->notifications()->findOrFail($notification);
        $n->markAsRead();

        return response()->json(new NotificationResource($n));
    }

    public function readAll(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
    }
}
