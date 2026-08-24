<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        $tier = $this->route('tier');
        return $campaign && $tier &&
               $campaign->user_id === auth()->id() &&
               $campaign->id === $tier->campaign_id;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'min_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quota' => ['sometimes', 'required', 'integer', 'min:0'],
            'reward_description' => ['nullable', 'string'],
        ];
    }
}
