<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user() ?? auth('sanctum')->user();

        return [
            'type' => ['nullable', 'string', 'in:payment,refund,disbursement,platform_fee,deposit,withdrawal'],
            'status' => ['nullable', 'string', 'in:pending,success,failed'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'user_id' => ($user && $user->role === 'admin') ? ['nullable', 'integer', 'exists:users,id'] : ['prohibited'],
            'sort' => ['nullable', 'string', 'in:latest,oldest'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
