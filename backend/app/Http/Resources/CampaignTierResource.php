<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "min_amount" => $this->min_amount,
            "quota" => $this->isUnlimited() ? null : $this->quota,
            "remaining_quota" => $this->isUnlimited() ? null : $this->remaining_quota,
            "is_unlimited" => $this->isUnlimited(),
            "has_availability" => $this->hasAvailability(),
            "reward_description" => $this->reward_description,
        ];
    }
}
