<?php

namespace App\Http\Requests\Pro;

use App\Http\Requests\ApiRequest;

/** `PUT /pro/schedule` : `{ days: WorkDay[7] }` avec pauses. */
class SaveScheduleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.isoDay' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.working' => ['required', 'boolean'],
            'days.*.start' => ['nullable', 'required_if:days.*.working,true', self::TIME],
            'days.*.end' => ['nullable', 'required_if:days.*.working,true', self::TIME],
            'days.*.breaks' => ['present', 'array', 'max:6'],
            'days.*.breaks.*.start' => ['required', self::TIME],
            'days.*.breaks.*.end' => ['required', self::TIME],
            'days.*.breaks.*.reason' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return ['days.size' => 'Les 7 jours de la semaine sont requis.'];
    }
}
