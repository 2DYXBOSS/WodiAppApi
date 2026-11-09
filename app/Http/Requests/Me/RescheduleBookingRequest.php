<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\ApiRequest;

class RescheduleBookingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', self::DATE],
            'time' => ['required', self::TIME],
            'staffId' => ['nullable', 'string', 'max:40'],
        ];
    }
}
