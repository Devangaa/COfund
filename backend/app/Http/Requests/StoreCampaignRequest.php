<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'unique:campaigns,slug'],
            'description' => ['required', 'string', 'max:10000'],
            'target_amount' => ['required', 'numeric', 'min:100000'],
            'deadline' => ['required', 'date', 'after:+7 days'],
            'video_url' => ['nullable', 'string', 'url'],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.name' => ['required', 'string', 'max:255'],
            'tiers.*.min_amount' => ['required', 'numeric', 'min:0'],
            'tiers.*.quota' => ['required', 'integer', 'min:0'],
            'tiers.*.reward_description' => ['nullable', 'string'],
        ];
    }
}
