<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\ApiRequest;

/** `GET /availability/days` (from, to) et `/availability/slots` (date). */
class AvailabilityRequest extends ApiRequest
{
    public function rules(): array
    {
        $isDays = $this->routeIs('availability.days');

        return [
            'serviceId' => ['required', 'uuid'],
            'staffId' => ['nullable', 'string', 'max:40'],
            'excludeBookingId' => ['nullable', 'uuid'],
            'from' => [$isDays ? 'required' : 'prohibited', self::DATE],
            'to' => [$isDays ? 'required' : 'prohibited', self::DATE, 'after_or_equal:from'],
            'date' => [$isDays ? 'prohibited' : 'required', self::DATE],
        ];
    }
}
