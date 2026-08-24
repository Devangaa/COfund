<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "user_id",
        "backing_id",
        "campaign_id",
        "type",
        "amount",
        "status",
        "reference",
    ];

    protected $casts = [
        "amount" => "decimal:2",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function backing()
    {
        return $this->belongsTo(Backing::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
