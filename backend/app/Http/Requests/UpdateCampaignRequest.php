<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        return $campaign && $campaign->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'unique:campaigns,slug,' . ($this->route('campaign')->id ?? 0)],
            'description' => ['sometimes', 'required', 'string'],
            'target_amount' => ['sometimes', 'required', 'numeric', 'min:100000'],
            'deadline' => ['sometimes', 'required', 'date', 'after:+7 days'],
            'video_url' => ['nullable', 'string', 'url'],
        ];
    }
}
