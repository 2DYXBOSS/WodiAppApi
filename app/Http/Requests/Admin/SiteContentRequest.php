<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class SiteContentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'presentation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'heroTitle' => ['sometimes', 'nullable', 'string', 'max:200'],
            'heroSubtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bannerImage' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', self::PHONE],
            'whatsapp' => ['sometimes', 'nullable', 'string', self::PHONE],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'address' => ['sometimes', 'nullable', 'string', 'max:300'],
            'socials' => ['sometimes', 'array'],
            'socials.instagram' => ['nullable', 'url', 'max:300'],
            'socials.facebook' => ['nullable', 'url', 'max:300'],
            'socials.tiktok' => ['nullable', 'url', 'max:300'],
        ];
    }

    /** Les textes vides restent des chaînes (contrat `SiteContent`). */
    public function content(): array
    {
        $data = $this->validated();
        foreach (['presentation', 'heroTitle', 'heroSubtitle'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (string) $data[$key];
            }
        }

        return $data;
    }
}
