<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "category_id",
        "title",
        "slug",
        "description",
        "target_amount",
        "collected_amount",
        "deadline",
        "status",
        "video_url",
        "rejection_note",
        "reviewed_by",
        "reviewed_at",
    ];

    protected $casts = [
        "target_amount" => "decimal:2",
        "collected_amount" => "decimal:2",
        "deadline" => "date",
        "reviewed_at" => "datetime",
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, "reviewed_by");
    }

    public function images()
    {
        return $this->hasMany(CampaignImage::class);
    }

    public function tiers()
    {
        return $this->hasMany(CampaignTier::class);
    }

    public function updates()
    {
        return $this->hasMany(CampaignUpdate::class);
    }

    public function backings()
    {
        return $this->hasMany(Backing::class);
    }
}
