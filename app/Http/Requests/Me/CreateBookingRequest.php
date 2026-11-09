<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\ApiRequest;

class CreateBookingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'serviceId' => ['required', 'uuid'],
            'staffId' => ['nullable', 'string', 'max:40'],
            'date' => ['required', self::DATE],
            'time' => ['required', self::TIME],
            'location' => ['nullable', 'in:salon,domicile'],
            'notes' => ['nullable', 'string', 'max:'.config('wodi.booking.max_notes_length')],
        ];
    }
}
