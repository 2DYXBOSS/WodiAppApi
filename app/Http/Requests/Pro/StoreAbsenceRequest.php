<?php

namespace App\Http\Requests\Pro;

use App\Enums\AbsenceType;
use App\Http\Requests\ApiRequest;

class StoreAbsenceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', AbsenceType::requestable())],
            'startDate' => ['required', self::DATE, 'after_or_equal:today'],
            'endDate' => ['required', self::DATE, 'after_or_equal:startDate'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'startDate.after_or_equal' => 'La date de début est déjà passée.',
            'endDate.after_or_equal' => 'La date de fin doit suivre la date de début.',
        ];
    }
}
