<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pwa_id',
        'user_id',
        'title',
        'content',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'helpful_count' => 'integer',
            'is_approved' => 'boolean',
        ];
    }

    public function pwa(): BelongsTo
    {
        return $this->belongsTo(Pwa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
