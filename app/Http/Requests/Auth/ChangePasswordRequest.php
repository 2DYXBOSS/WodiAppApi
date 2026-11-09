<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class ChangePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'max:200'],
            'newPassword' => ['required', 'string', 'max:200', 'different:currentPassword', self::password()],
        ];
    }

    public function messages(): array
    {
        return ['newPassword.different' => 'Le nouveau mot de passe doit être différent de l’actuel.'];
    }
}
