<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        return $campaign && $campaign->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:campaign_tiers,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $campaign = $this->route('campaign');
        if ($campaign) {
            $validTierIds = $campaign->tiers()->pluck('id')->toArray();
            $requestedIds = $this->input('ids', []);
            foreach ($requestedIds as $id) {
                if (! in_array($id, $validTierIds)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException();
                }
            }
        }
    }
}
