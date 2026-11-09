<?php

namespace App\Http\Resources;

use App\Models\Professional;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /team` — union `TeamMember` (vitrine) et `Barber` (mobile). Aucune donnée privée
 * (téléphone, e-mail, planning détaillé) n'est exposée.
 * Nécessite `services` (prestations réservables activées) et `workingHours` préchargés.
 *
 * @mixin Professional
 */
class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->fullName(),
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'role' => $this->job_title,
            'jobTitle' => $this->job_title,
            'specialties' => $this->specialties ?? [],
            'bio' => $this->bio,
            'photo' => Media::url($this->photo),
            'photoAlt' => $this->photo_alt ?? 'Portrait de '.$this->fullName(),
            'serviceIds' => $this->services->filter(fn ($s) => $s->isBookable())->pluck('id')->values()->all(),
            'rating' => null, // aucun système d'avis à ce jour
            'workingDays' => $this->workingHours->filter(fn ($h) => $h->is_working)->pluck('iso_day')->values()->all(),
            'isPlaceholder' => $this->is_placeholder,
        ];
    }
}
