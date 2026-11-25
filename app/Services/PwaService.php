<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Pwa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PwaService
{
    /**
     * Escape special characters (% and _ and \) for SQL LIKE expressions.
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Get paginated published PWAs with optional filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPublishedPwas(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Pwa::published()
            ->select([
                'id',
                'category_id',
                'name',
                'slug',
                'short_description',
                'icon_url',
                'version',
                'rating_average',
                'review_count',
                'install_count',
                'created_at',
            ])
            ->with(['category:id,name,name_ar,slug']);

        // Search in name and short_description
        if (! empty($filters['search'])) {
            $escapedTerm = $this->escapeLike(trim($filters['search']));
            $likePattern = "%{$escapedTerm}%";
            $driver = $query->getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                $query->where(function ($subQuery) use ($likePattern): void {
                    $subQuery->whereRaw("name LIKE ? ESCAPE '\\'", [$likePattern])
                        ->orWhereRaw("short_description LIKE ? ESCAPE '\\'", [$likePattern]);
                });
            } else {
                $query->where(function ($subQuery) use ($likePattern): void {
                    $subQuery->where('name', 'like', $likePattern)
                        ->orWhere('short_description', 'like', $likePattern);
                });
            }
        }

        // Category filter (includes children categories if parent is selected)
        if (! empty($filters['category'])) {
            $category = Category::where('slug', $filters['category'])
                ->with('children:id,parent_id')
                ->first();

            if ($category) {
                $categoryIds = array_merge([$category->id], $category->children->pluck('id')->all());
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Sorting
        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'popular':
                $query->orderBy('install_count', 'desc')
                    ->orderBy('id', 'desc');
                break;
            case 'top_rated':
                $query->orderBy('rating_average', 'desc')
                    ->orderBy('review_count', 'desc')
                    ->orderBy('id', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc');
                break;
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a single published PWA by its slug.
     * Throws 404 if not found or not published.
     */
    public function getPublishedPwaBySlug(string $slug): Pwa
    {
        $pwa = Pwa::published()
            ->where('slug', $slug)
            ->with([
                'category:id,name,name_ar,slug',
                'developer:id,first_name,last_name,username,avatar_url',
            ])
            ->first();

        if (! $pwa) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        return $pwa;
    }

    /**
     * Get approved reviews for a published PWA.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPwaReviews(string $slug, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        // Ensure PWA is published or throw 404
        $pwa = Pwa::published()->where('slug', $slug)->first();

        if (! $pwa) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        $query = $pwa->reviews()
            ->where('is_approved', true)
            ->select(['id', 'pwa_id', 'user_id', 'title', 'content', 'rating', 'helpful_count', 'created_at'])
            ->with(['user:id,first_name,username,avatar_url']);

        $sort = $filters['sort'] ?? 'newest';
        if ($sort === 'helpful') {
            $query->orderBy('helpful_count', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
        } else {
            $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
        }

        return $query->paginate($perPage);
    }
}
