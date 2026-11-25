<?php

namespace App\Policies;

use App\Enums\PwaStatus;
use App\Models\Pwa;
use App\Models\User;

class PwaPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'review') {
            return null;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Pwa $pwa): bool
    {
        if ($pwa->is_published && $pwa->status === PwaStatus::Approved) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->id === $pwa->developer_id || $user->hasRole('moderator');
    }

    public function create(User $user): bool
    {
        return $user->can('pwas.submit') || $user->hasRole('developer');
    }

    public function update(User $user, Pwa $pwa): bool
    {
        if ($user->id !== $pwa->developer_id) {
            return false;
        }

        return in_array($pwa->status, [PwaStatus::Draft, PwaStatus::Rejected], true);
    }

    public function delete(User $user, Pwa $pwa): bool
    {
        return $user->id === $pwa->developer_id;
    }

    public function review(User $user, Pwa $pwa): bool
    {
        if ($user->id === $pwa->developer_id) {
            return false;
        }

        return $user->can('pwas.review') || $user->hasRole(['moderator', 'admin']);
    }

    public function install(User $user, Pwa $pwa): bool
    {
        return $user->can('pwas.install') && $pwa->is_published && $pwa->status === PwaStatus::Approved;
    }
}
