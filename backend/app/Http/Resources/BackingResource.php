<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign' => $this->whenLoaded('campaign', function () {
                $campaign = $this->campaign;
                $target = (float) $campaign->target_amount;
                $collected = (float) $campaign->collected_amount;

                return [
                    'id' => $campaign->id,
                    'slug' => $campaign->slug,
                    'title' => $campaign->title,
                    'status' => $campaign->status,
                    'target_amount' => $campaign->target_amount,
                    'collected_amount' => $campaign->collected_amount,
                    'progress_percentage' => $target > 0
                        ? round(($collected / $target) * 100, 2)
                        : 0,
                    'deadline' => $campaign->deadline,
                    'creator_name' => $campaign->creator->name ?? null,
                ];
            }),
            'tier' => $this->whenLoaded('tier', function () {
                return $this->tier ? [
                    'id' => $this->tier->id,
                    'name' => $this->tier->name,
                    'min_amount' => $this->tier->min_amount,
                ] : null;
            }),
            'amount' => $this->amount,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
