<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        return $campaign && $campaign->user_id === auth()->id();
    }

    protected function prepareForValidation(): void
    {
        $campaign = $this->route('campaign');
        if ($campaign && $campaign->user_id !== auth()->id()) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
        ];
    }
}
