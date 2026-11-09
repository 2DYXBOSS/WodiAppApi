<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

/** `PUT /admin/staff/:id/schedule` : `WeeklySchedule { staffId, days: { isoDay, start|null, end|null }[] }`. */
class AdminScheduleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.isoDay' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.start' => ['nullable', self::TIME],
            'days.*.end' => ['nullable', self::TIME],
            'days.*.breaks' => ['sometimes', 'array'],
            'days.*.breaks.*.start' => ['required', self::TIME],
            'days.*.breaks.*.end' => ['required', self::TIME],
        ];
    }
}
