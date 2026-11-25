<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pwa_id',
        'moderator_id',
        'action',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function pwa(): BelongsTo
    {
        return $this->belongsTo(Pwa::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
