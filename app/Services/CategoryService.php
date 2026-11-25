<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    /**
     * Cache key for public categories tree.
     */
    public const CACHE_KEY = Category::CACHE_KEY;

    /**
     * Cache duration: 10 minutes in seconds.
     */
    public const CACHE_TTL_SECONDS = 600;

    /**
     * Retrieve the active categories tree with published PWAs count (cached for 10 minutes).
     */
    public function getActiveCategoriesTree(): Collection
    {
        try {
            return Cache::remember(Category::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): Collection {
                return $this->fetchCategoriesTree();
            });
        } catch (\Throwable) {
            return $this->fetchCategoriesTree();
        }
    }

    /**
     * Query the active categories tree with published PWAs count directly from database.
     */
    public function fetchCategoriesTree(): Collection
    {
        return Category::active()
            ->whereNull('parent_id')
            ->select(['id', 'name', 'name_ar', 'slug', 'description', 'icon_url', 'sort_order'])
            ->withCount(['pwas' => function ($query): void {
                $query->published();
            }])
            ->with([
                'children' => function ($query): void {
                    $query->active()
                        ->select(['id', 'parent_id', 'name', 'name_ar', 'slug', 'description', 'icon_url', 'sort_order'])
                        ->withCount(['pwas' => function ($subQuery): void {
                            $subQuery->published();
                        }])
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Invalidate categories cache manually.
     */
    public function clearCategoriesCache(): void
    {
        static::clearCache();
    }

    /**
     * Clear cached categories tree statically.
     */
    public static function clearCache(): void
    {
        Cache::forget(Category::CACHE_KEY);
    }
}
