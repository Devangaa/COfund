<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignTier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "campaign_id",
        "name",
        "min_amount",
        "quota",
        "remaining_quota",
        "reward_description",
    ];

    protected $casts = [
        "min_amount" => "decimal:2",
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function backings()
    {
        return $this->hasMany(Backing::class, "tier_id");
    }

    public function isUnlimited(): bool
    {
        return $this->quota === 0;
    }

    public function hasAvailability(): bool
    {
        if ($this->isUnlimited()) {
            return true;
        }
        return $this->remaining_quota > 0;
    }
}
