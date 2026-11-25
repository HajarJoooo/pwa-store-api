<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('reviews.create');
    }

    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id && $user->can('reviews.update-own');
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id && $user->can('reviews.delete-own');
    }
}
