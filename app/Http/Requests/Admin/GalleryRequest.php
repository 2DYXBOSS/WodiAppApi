<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class GalleryRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'category' => [$required, 'in:coupes,barbe,salon,details'],
            'url' => [$required, 'string', 'max:500'],
            'published' => ['sometimes', 'boolean'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'orientation' => ['sometimes', 'in:portrait,landscape'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'isIllustration' => ['sometimes', 'boolean'],
        ];
    }
}
