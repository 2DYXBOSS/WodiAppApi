<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class AdminUnavailabilityRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'staffId' => [$required, 'uuid', 'exists:professionals,id'],
            'type' => [$required, 'in:conge,absence,formation,autre,exception'],
            'startDate' => [$required, self::DATE],
            'endDate' => [$required, self::DATE, 'after_or_equal:startDate'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
