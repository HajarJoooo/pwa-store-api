<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'pwa_id',
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function pwa(): BelongsTo
    {
        return $this->belongsTo(Pwa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

