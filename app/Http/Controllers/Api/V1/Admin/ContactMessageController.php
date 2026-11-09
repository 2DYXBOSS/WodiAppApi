<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Messages reçus via le formulaire du site (notifications.view). Pas encore d'écran dans le back-office. */
class ContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = ContactMessage::query();
        $status = $request->query('status');
        $status ? $q->where('status', $status) : $q->where('status', '!=', 'spam');
        Page::search($q, $request->query('search'), ['name', 'email', 'subject']);

        return response()->json(Page::make($q, $request, fn ($m) => (new ContactMessageResource($m))->resolve($request),
            ['createdAt' => 'created_at'], fn ($q) => $q->orderByDesc('created_at')));
    }

    public function show(string $message): JsonResponse
    {
        return response()->json(new ContactMessageResource(ContactMessage::query()->findOrFail($message)));
    }

    public function read(string $message): JsonResponse
    {
        $m = ContactMessage::query()->findOrFail($message);
        $m->update(['read_at' => $m->read_at ?? now(), 'status' => $m->status === 'new' ? 'read' : $m->status]);

        return response()->json(new ContactMessageResource($m));
    }

    public function status(Request $request, string $message): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:new,read,handled,spam']]);
        $m = ContactMessage::query()->findOrFail($message);
        $m->update(['status' => $data['status'], 'read_at' => $m->read_at ?? now()]);

        return response()->json(new ContactMessageResource($m));
    }
}
