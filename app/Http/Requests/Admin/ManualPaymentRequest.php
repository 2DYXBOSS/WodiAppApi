<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class ManualPaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reservationId' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
