<?php

namespace App\Http\Resources;

use App\Models\BookingStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingStatusHistory */
class HistoryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'at' => $this->created_at?->toIso8601ZuluString(),
            'by' => $this->changed_by_name,
            'action' => $this->action,
            'detail' => $this->detail,
        ];
    }
}
