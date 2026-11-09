<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\RegisterDeviceRequest;
use App\Http\Resources\NotificationResource;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Notifications in-app, préférences et appareils (tous rôles). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(100)->get();

        return response()->json(NotificationResource::collection($notifications));
    }

    public function read(Request $request, string $notification): Response
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->noContent();
    }

    public function readAll(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json($this->prefs($request));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bookingUpdates' => ['required', 'boolean'],
            'reminders' => ['required', 'boolean'],
            'promotions' => ['required', 'boolean'],
        ]);
        $request->user()->update([
            'notify_booking_updates' => $data['bookingUpdates'],
            'notify_reminders' => $data['reminders'],
            'notify_promotions' => $data['promotions'],
        ]);

        return response()->json($this->prefs($request));
    }

    /** Enregistre un jeton Expo Push (réattribué si l'appareil change de compte). */
    public function registerDevice(RegisterDeviceRequest $request): Response
    {
        DeviceToken::query()->updateOrCreate(
            ['token' => $request->input('token')],
            ['user_id' => $request->user()->id, 'platform' => $request->input('platform'), 'last_used_at' => now()],
        );

        return response()->noContent();
    }

    public function unregisterDevice(Request $request, string $token): Response
    {
        $request->user()->deviceTokens()->where('token', $token)->delete();

        return response()->noContent();
    }

    private function prefs(Request $request): array
    {
        $user = $request->user()->fresh();

        return [
            'bookingUpdates' => $user->notify_booking_updates,
            'reminders' => $user->notify_reminders,
            'promotions' => $user->notify_promotions,
        ];
    }
}
