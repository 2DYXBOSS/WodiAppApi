<?php

namespace App\Http\Requests\Pro;

use App\Http\Requests\ApiRequest;

class StoreUnavailabilityRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', self::DATE, 'after_or_equal:today'],
            'start' => ['required', self::TIME],
            'end' => ['required', self::TIME],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['date.after_or_equal' => 'La date est déjà passée.'];
    }
}
