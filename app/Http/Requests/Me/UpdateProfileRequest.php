<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\ApiRequest;

/** `PATCH /me` : rôle, permissions et e-mail ne sont jamais modifiables ici. */
class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', self::PHONE],
            'birthDate' => ['nullable', self::DATE, 'before_or_equal:today'],
        ];
    }
}
