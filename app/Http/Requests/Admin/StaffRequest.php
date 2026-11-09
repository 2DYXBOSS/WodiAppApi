<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class StaffRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'firstName' => [$required, 'string', 'max:100'],
            'lastName' => [$required, 'string', 'max:100'],
            'photo' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', self::PHONE],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'jobTitle' => [$required, 'string', 'max:100'],
            'specialties' => ['sometimes', 'array', 'max:8'],
            'specialties.*' => ['string', 'max:60'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
            'bookable' => ['sometimes', 'boolean'],
            'public' => ['sometimes', 'boolean'],
            'serviceIds' => ['sometimes', 'array'],
            'serviceIds.*' => ['uuid', 'exists:services,id'],
        ];
    }
}
