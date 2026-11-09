<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class MediaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('wodi.media.max_kb'), 'mimetypes:'.implode(',', config('wodi.media.mimes')),
                'dimensions:max_width=8000,max_height=8000'],
            'folder' => ['nullable', 'in:gallery,team,services,categories,brand,content'],
        ];
    }
}
