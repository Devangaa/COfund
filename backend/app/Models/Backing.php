<?php

namespace App\Models;

use App\Enums\BackingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'tier_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => BackingStatus::class,
    ];

    public function backer()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function tier()
    {
        return $this->belongsTo(CampaignTier::class, "tier_id");
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
