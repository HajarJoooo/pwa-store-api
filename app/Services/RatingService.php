<?php

namespace App\Services;

use App\Models\Pwa;
use App\Models\Review;

class RatingService
{
    /**
     * Recalculate rating_average and review_count for a PWA.
     * Computed directly from active, approved, non-deleted reviews.
     */
    public function recalculate(Pwa $pwa): Pwa
    {
        $stats = Review::where('pwa_id', $pwa->id)
            ->where('is_approved', true)
            ->selectRaw('COUNT(*) as total_count, AVG(rating) as avg_rating')
            ->first();

        $count = (int) ($stats->total_count ?? 0);
        $average = $count > 0 ? round((float) $stats->avg_rating, 2) : 0.00;

        $pwa->forceFill([
            'rating_average' => $average,
            'review_count' => $count,
        ])->save();

        return $pwa;
    }
}
