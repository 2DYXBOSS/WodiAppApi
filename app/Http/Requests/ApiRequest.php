<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Base des Form Requests : l'autorisation est contrôlée par les middlewares de route. */
abstract class ApiRequest extends FormRequest
{
    public const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    public const DATE = 'date_format:Y-m-d';

    public const PHONE = 'regex:/^\+?[0-9 ().-]{8,20}$/';

    public function authorize(): bool
    {
        return true;
    }

    public static function password(): Password
    {
        // Aligné sur les frontends (8 caractères min.) ; la longueur prime sur la complexité (NIST 800-63B).
        return Password::min(8)->max(200);
    }

    /** Supprime les espaces autour des chaînes et convertit les chaînes vides en null. */
    protected function prepareForValidation(): void
    {
        $this->replace($this->clean($this->all()));
    }

    private function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && (stripos($key, 'password') !== false || stripos($key, 'token') !== false)) {
                continue; // secrets transmis tels quels
            }
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' ? null : $value;
            } elseif (is_array($value)) {
                $data[$key] = $this->clean($value);
            }
        }

        return $data;
    }
}
