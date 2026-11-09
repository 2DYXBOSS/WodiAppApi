<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('client');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'firstName' => [$required, 'string', 'max:100'],
            'lastName' => [$required, 'string', 'max:100'],
            'phone' => [$required, 'string', self::PHONE, Rule::unique('customers', 'phone')->ignore($id)->whereNull('deleted_at')],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'preferences' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'birthDate' => ['sometimes', 'nullable', self::DATE],
        ];
    }

    public function messages(): array
    {
        return ['phone.unique' => 'Un client existe déjà avec ce numéro.'];
    }
}
