<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** `StaffMember` (back-office). @mixin \App\Models\Professional */
class StaffMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'photo' => Media::url($this->photo),
            'phone' => $this->phone,
            'email' => $this->email,
            'jobTitle' => $this->job_title,
            'specialties' => $this->specialties ?? [],
            'bio' => $this->bio,
            'active' => $this->is_active,
            'bookable' => $this->is_bookable,
            'public' => $this->is_public,
            'hasAccount' => $this->user_id !== null,
            'serviceIds' => $this->whenLoaded('services', fn () => $this->services->pluck('id')->values()->all()),
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
