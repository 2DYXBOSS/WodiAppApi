<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\ServiceCategory;

class CategoryRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'icon' => ['sometimes', 'nullable', 'in:'.implode(',', ServiceCategory::ICONS)],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'imageAlt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
