<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notification in-app — union mobile (`bookingId`) et back-office (`link`).
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? $this->type,
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'read' => $this->read_at !== null,
            'bookingId' => $this->data['bookingId'] ?? null,
            'link' => $this->data['link'] ?? null,
        ];
    }
}
