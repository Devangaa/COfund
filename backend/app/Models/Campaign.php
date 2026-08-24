<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

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

    protected static function booted()
    {
        static::saving(function (self $campaign) {
            if (empty($campaign->slug)) {
                $campaign->slug = Str::slug($campaign->title);
            }

            $originalSlug = $campaign->slug;
            $counter = 1;
            while (static::withoutTrashed()->where('slug', $campaign->slug)->where('id', '!=', $campaign->id)->exists()) {
                $campaign->slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        });
    }

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
