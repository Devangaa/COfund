<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCampaignImageRequest extends FormRequest
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
            'ids.*' => ['required', 'integer', 'exists:campaign_images,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $campaign = $this->route('campaign');
        if ($campaign) {
            $validImageIds = $campaign->images()->pluck('id')->toArray();
            $requestedIds = $this->input('ids', []);
            foreach ($requestedIds as $id) {
                if (! in_array($id, $validImageIds)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException();
                }
            }
        }
    }
}
