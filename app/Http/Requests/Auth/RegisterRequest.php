<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

/** Inscription client. Tout champ `role` / `permissions` est ignoré (jamais validé ni lu). */
class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['required', 'string', self::PHONE],
            'password' => ['required', 'string', 'max:200', self::password()],
        ];
    }
}
