<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tier_id' => ['nullable', 'integer', 'exists:campaign_tiers,id'],
            'amount' => ['required', 'numeric', 'min:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $campaign = $this->route('campaign');
        $user = $this->user();

        if (! $campaign) {
            return;
        }

        if ($campaign->user_id === $user?->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Creator cannot back their own campaign'
            );
        }

        if ($campaign->status !== CampaignStatus::ACTIVE) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'campaign' => 'Campaign must be active to receive backing',
            ]);
        }
    }
}
