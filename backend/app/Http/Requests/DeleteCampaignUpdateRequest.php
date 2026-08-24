<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCampaignUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        $update = $this->route('update');
        return $campaign && $update &&
               $campaign->user_id === auth()->id() &&
               $campaign->id === $update->campaign_id;
    }

    public function rules(): array
    {
        return [];
    }
}
