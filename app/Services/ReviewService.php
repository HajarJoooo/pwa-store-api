<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewService
{
    public function __construct(
        protected RatingService $ratingService
    ) {}

    /**
     * Create or restore a review for a published PWA.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws NotFoundHttpException
     */
    public function createReview(User $user, string $slug, array $data): Review
    {
        $pwa = Pwa::published()->where('slug', $slug)->first();

        if (! $pwa) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        // Developer cannot review their own PWA
        if ($pwa->developer_id === $user->id) {
            throw ValidationException::withMessages([
                'pwa' => [__('messages.cannot_review_own_pwa')],
            ]);
        }

        // Check installation prerequisite if enabled in config
        if (config('reviews.require_install', true)) {
            $hasInstalled = Installation::where('user_id', $user->id)
                ->where('pwa_id', $pwa->id)
                ->exists();

            if (! $hasInstalled) {
                throw ValidationException::withMessages([
                    'pwa' => [__('messages.install_required_for_review')],
                ]);
            }
        }

        // Check for existing review (including soft-deleted)
        $existing = Review::withTrashed()
            ->where('pwa_id', $pwa->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages([
                'review' => [__('messages.already_reviewed')],
            ]);
        }

        return DB::transaction(function () use ($user, $pwa, $data, $existing): Review {
            if ($existing && $existing->trashed()) {
                $existing->restore();
                $existing->fill([
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'] ?? null,
                ]);
                $existing->is_approved = true;
                $existing->save();
                $review = $existing;
            } else {
                $review = new Review([
                    'pwa_id' => $pwa->id,
                    'user_id' => $user->id,
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'] ?? null,
                ]);
                $review->is_approved = true;
                $review->save();
            }

            // Recalculate average and count within the same transaction
            $this->ratingService->recalculate($pwa);
            CategoryService::clearCache();

            return $review->load('user');
        });
    }

    /**
     * Update an existing review owned by the user.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function updateReview(User $user, int $id, array $data): Review
    {
        $review = Review::find($id);

        if (! $review) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        if ($user->cannot('update', $review)) {
            throw new AccessDeniedHttpException(__('messages.unauthorized'));
        }

        return DB::transaction(function () use ($review, $data): Review {
            if (array_key_exists('rating', $data)) {
                $review->rating = (int) $data['rating'];
            }
            if (array_key_exists('title', $data)) {
                $review->title = $data['title'];
            }
            if (array_key_exists('content', $data)) {
                $review->content = $data['content'];
            }

            $review->save();

            $this->ratingService->recalculate($review->pwa);
            CategoryService::clearCache();

            return $review->load('user');
        });
    }

    /**
     * Soft delete a review owned by the user.
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function deleteReview(User $user, int $id): void
    {
        $review = Review::find($id);

        if (! $review) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        if ($user->cannot('delete', $review)) {
            throw new AccessDeniedHttpException(__('messages.unauthorized'));
        }

        DB::transaction(function () use ($review): void {
            $pwa = $review->pwa;
            $review->delete();

            $this->ratingService->recalculate($pwa);
            CategoryService::clearCache();
        });
    }
}
