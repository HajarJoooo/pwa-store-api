<?php

namespace App\Models;

use Database\Factories\InstallationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pwa_id',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pwa(): BelongsTo
    {
        return $this->belongsTo(Pwa::class);
    }
}
