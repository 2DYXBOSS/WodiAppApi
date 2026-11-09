<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\ApiRequest;

class RegisterDeviceRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:255'], 'platform' => ['nullable', 'in:ios,android,web']];
    }
}
