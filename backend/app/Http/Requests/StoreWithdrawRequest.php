<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->email_verified_at !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:50000', 'max:50000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum withdrawal is Rp50,000',
            'amount.max' => 'Maximum withdrawal per transaction is Rp50,000,000',
        ];
    }
}
