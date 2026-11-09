<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class ServiceRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'categoryId' => [$required, 'uuid', 'exists:service_categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'priceFrom' => ['sometimes', 'boolean'],
            'durationMinutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:720'],
            'active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'locations' => ['sometimes', 'array', 'min:1'],
            'locations.*' => ['in:salon,domicile', 'distinct'],
            'conditions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'bookableOnline' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'staffIds' => ['sometimes', 'array'],
            'staffIds.*' => ['uuid', 'exists:professionals,id'],
        ];
    }
}
