<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpgradeToCreatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User must be authenticated and be a backer
        return $this->user() && $this->user()->role === 'backer';
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan upgrade ke creator wajib diisi',
            'reason.string' => 'Alasan harus berupa teks',
            'reason.max' => 'Alasan maksimal 500 karakter',
        ];
    }
}
