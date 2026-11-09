<?php

namespace App\Http\Requests\Pro;

use App\Http\Requests\ApiRequest;

/** Seuls les champs autorisés sont acceptés : nom, poste et statut restent gérés par le back-office. */
class UpdateProProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'nullable', 'string', self::PHONE],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'specialties' => ['sometimes', 'array', 'max:8'],
            'specialties.*' => ['string', 'max:60'],
        ];
    }
}
