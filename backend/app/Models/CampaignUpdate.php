<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignUpdate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "campaign_id",
        "title",
        "content",
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
