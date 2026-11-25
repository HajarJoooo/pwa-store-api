<?php

namespace App\Services;

use App\Enums\PwaStatus;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;

class DeveloperDashboardService
{
    /**
     * Get aggregate metrics and top items for developer dashboard.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(User $developer): array
    {
        // 1. Grouped query for status counts, total installs, total reviews, and weighted rating
        $statusStats = Pwa::where('developer_id', $developer->id)
            ->selectRaw('
                status,
                COUNT(*) as count,
                SUM(install_count) as sum_installs,
                SUM(review_count) as sum_reviews,
                SUM(CASE WHEN status = ? THEN rating_average * review_count ELSE 0 END) as sum_weighted_rating,
                SUM(CASE WHEN status = ? THEN review_count ELSE 0 END) as sum_approved_reviews
            ', [PwaStatus::Approved->value, PwaStatus::Approved->value])
            ->groupBy('status')
            ->get();

        $counts = [
            'draft' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        $totalInstalls = 0;
        $totalReviews = 0;
        $sumWeightedRating = 0.0;
        $sumApprovedReviews = 0;

        foreach ($statusStats as $stat) {
            $statusKey = $stat->status instanceof PwaStatus ? $stat->status->value : (string) $stat->status;
            if (array_key_exists($statusKey, $counts)) {
                $counts[$statusKey] = (int) $stat->count;
            }
            $totalInstalls += (int) $stat->sum_installs;
            $totalReviews += (int) $stat->sum_reviews;
            $sumWeightedRating += (float) $stat->sum_weighted_rating;
            $sumApprovedReviews += (int) $stat->sum_approved_reviews;
        }

        $averageRating = $sumApprovedReviews > 0
            ? round($sumWeightedRating / $sumApprovedReviews, 2)
            : 0.00;

        // 2. Top 5 PWAs by installs
        $topPwas = Pwa::where('developer_id', $developer->id)
            ->select([
                'id',
                'name',
                'slug',
                'icon_url',
                'install_count',
                'rating_average',
                'review_count',
                'status',
            ])
            ->orderByDesc('install_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Pwa $pwa) => [
                'id' => $pwa->id,
                'name' => $pwa->name,
                'slug' => $pwa->slug,
                'icon_url' => $pwa->icon_url,
                'install_count' => (int) $pwa->install_count,
                'rating_average' => (float) $pwa->rating_average,
                'review_count' => (int) $pwa->review_count,
            ]);

        // 3. Recent 5 reviews on developer's PWAs via a single join query
        $recentReviews = Review::join('pwas', 'reviews.pwa_id', '=', 'pwas.id')
            ->join('users', 'reviews.user_id', '=', 'users.id')
            ->where('pwas.developer_id', $developer->id)
            ->where('reviews.is_approved', true)
            ->whereNull('reviews.deleted_at')
            ->select([
                'reviews.id',
                'reviews.rating',
                'reviews.title',
                'reviews.content',
                'reviews.created_at',
                'pwas.id as pwa_id',
                'pwas.name as pwa_name',
                'pwas.slug as pwa_slug',
                'users.first_name as reviewer_first_name',
                'users.username as reviewer_username',
                'users.avatar_url as reviewer_avatar_url',
            ])
            ->orderByDesc('reviews.id')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'rating' => (int) $r->rating,
                'title' => $r->title,
                'content' => $r->content,
                'pwa' => [
                    'id' => (int) $r->pwa_id,
                    'name' => $r->pwa_name,
                    'slug' => $r->pwa_slug,
                ],
                'reviewer' => [
                    'first_name' => $r->reviewer_first_name,
                    'username' => $r->reviewer_username,
                    'avatar_url' => $r->reviewer_avatar_url,
                ],
                'created_at' => $r->created_at instanceof \DateTimeInterface
                    ? $r->created_at->format(\DateTimeInterface::ATOM)
                    : (string) $r->created_at,
            ]);

        return [
            'counts' => $counts,
            'total_installs' => $totalInstalls,
            'total_reviews' => $totalReviews,
            'average_rating' => $averageRating,
            'top_pwas' => $topPwas,
            'recent_reviews' => $recentReviews,
        ];
    }
}
