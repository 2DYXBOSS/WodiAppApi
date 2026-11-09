<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class ResetPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:200'], 'password' => ['required', 'string', 'max:200', self::password()]];
    }
}
