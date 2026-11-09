<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class AdminUserRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $id = $this->route('user');

        return [
            'firstName' => [$required, 'string', 'max:100'],
            'lastName' => [$required, 'string', 'max:100'],
            'email' => [$required, 'email', 'max:191', Rule::unique('users', 'email')->ignore($id)],
            'role' => [$required, Rule::in(Role::adminRoles())],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Un compte existe déjà avec cette adresse.'];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }
}
