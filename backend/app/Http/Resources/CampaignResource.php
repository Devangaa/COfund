<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "creator" => new UserResource($this->whenLoaded('creator')),
            "category" => new CategoryResource($this->whenLoaded('category')),
            "title" => $this->title,
            "slug" => $this->slug,
            "description" => $this->description,
            "target_amount" => $this->target_amount,
            "collected_amount" => $this->collected_amount,
            "progress_percentage" => $this->target_amount > 0
                ? round(($this->collected_amount / $this->target_amount) * 100, 2)
                : 0,
            "deadline" => $this->deadline,
            "status" => $this->status,
            "video_url" => $this->video_url,
            "rejection_note" => $this->rejection_note,
            "reviewed_at" => $this->reviewed_at,
            "images" => CampaignImageResource::collection($this->whenLoaded('images')),
            "tiers" => CampaignTierResource::collection($this->whenLoaded('tiers')),
            "updates" => CampaignUpdateResource::collection($this->whenLoaded('updates')),
            "updates_count" => $this->updates_count ?? $this->updates()->count(),
        ];
    }
}
