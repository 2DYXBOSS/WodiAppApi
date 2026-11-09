<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class PromotionRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discountType' => [$required, 'in:percent,amount'],
            'value' => [$required, 'integer', 'min:1', $this->input('discountType') === 'percent' ? 'max:100' : 'max:10000000'],
            'startDate' => [$required, self::DATE],
            'endDate' => [$required, self::DATE, 'after_or_equal:startDate'],
            'conditions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
