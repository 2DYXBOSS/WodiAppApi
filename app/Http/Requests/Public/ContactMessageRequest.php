<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\ApiRequest;

class ContactMessageRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['nullable', 'string', self::PHONE],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Pot de miel anti-spam : champ invisible pour un humain.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
