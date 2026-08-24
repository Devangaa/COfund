<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        return $campaign && $campaign->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', 'min:0'],
            'reward_description' => ['nullable', 'string'],
        ];
    }
}
