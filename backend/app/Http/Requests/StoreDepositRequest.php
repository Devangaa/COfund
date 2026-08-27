<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->email_verified_at !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:10000', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum deposit is Rp10,000',
            'amount.max' => 'Maximum deposit per transaction is Rp100,000,000',
        ];
    }
}
