<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class ReservationRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'clientId' => [$required, 'uuid'],
            'serviceId' => [$required, 'uuid'],
            'staffId' => [$required, 'uuid'],
            'date' => [$required, self::DATE],
            'time' => [$required, self::TIME],
            'location' => ['sometimes', 'nullable', 'in:salon,domicile'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
