<?php

namespace App\Services;

use App\Enums\PwaStatus;
use App\Models\ModerationLog;
use App\Models\Pwa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModerationService
{
    /**
     * Escape special characters (% and _ and \) for SQL LIKE expressions.
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Get pending PWAs for review in FIFO order (oldest first).
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPendingPwas(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        $query = Pwa::where('status', PwaStatus::Pending)
            ->with([
                'category:id,name,name_ar,slug',
                'developer:id,first_name,last_name,username',
            ]);

        if (! empty($filters['search'])) {
            $escaped = $this->escapeLike(trim($filters['search']));
            $likePattern = "%{$escaped}%";
            $driver = $query->getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                $query->whereRaw("name LIKE ? ESCAPE '\\'", [$likePattern]);
            } else {
                $query->where('name', 'like', $likePattern);
            }
        }

        // FIFO: Oldest submitted applications first
        return $query->orderBy('id', 'asc')->paginate($perPage);
    }

    /**
     * Get single PWA details for moderator review.
     *
     * @throws NotFoundHttpException
     */
    public function getPwaForModerator(int $id): Pwa
    {
        $pwa = Pwa::with([
            'category:id,name,name_ar,slug',
            'developer:id,first_name,last_name,username',
        ])->find($id);

        if (! $pwa) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        return $pwa;
    }

    /**
     * Approve a pending PWA and publish it.
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     * @throws ValidationException
     */
    public function approvePwa(User $moderator, int $id): Pwa
    {
        return DB::transaction(function () use ($moderator, $id): Pwa {
            $pwa = Pwa::where('id', $id)
                ->lockForUpdate()
                ->first();

            if (! $pwa) {
                throw new NotFoundHttpException(__('messages.not_found'));
            }

            // Moderator cannot review their own application
            if ($moderator->id === $pwa->developer_id) {
                throw new AccessDeniedHttpException(__('messages.cannot_review_own_pwa_as_moderator'));
            }

            if ($pwa->status !== PwaStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.pwa_not_pending')],
                ]);
            }

            $pwa->forceFill([
                'status' => PwaStatus::Approved,
                'is_published' => true,
                'reviewed_by' => $moderator->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            ModerationLog::create([
                'pwa_id' => $pwa->id,
                'moderator_id' => $moderator->id,
                'action' => 'approved',
                'reason' => null,
                'created_at' => now(),
            ]);

            // Invalidate categories cache as pwas_count changes
            CategoryService::clearCache();

            return $pwa->fresh([
                'category:id,name,name_ar,slug',
                'developer:id,first_name,last_name,username',
            ]);
        });
    }

    /**
     * Reject a pending PWA with a sanitized reason.
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     * @throws ValidationException
     */
    public function rejectPwa(User $moderator, int $id, string $reason): Pwa
    {
        return DB::transaction(function () use ($moderator, $id, $reason): Pwa {
            $pwa = Pwa::where('id', $id)
                ->lockForUpdate()
                ->first();

            if (! $pwa) {
                throw new NotFoundHttpException(__('messages.not_found'));
            }

            // Moderator cannot review their own application
            if ($moderator->id === $pwa->developer_id) {
                throw new AccessDeniedHttpException(__('messages.cannot_review_own_pwa_as_moderator'));
            }

            if ($pwa->status !== PwaStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.pwa_not_pending')],
                ]);
            }

            $cleanReason = trim(strip_tags($reason));

            $pwa->forceFill([
                'status' => PwaStatus::Rejected,
                'is_published' => false,
                'reviewed_by' => $moderator->id,
                'reviewed_at' => now(),
                'rejection_reason' => $cleanReason,
            ])->save();

            ModerationLog::create([
                'pwa_id' => $pwa->id,
                'moderator_id' => $moderator->id,
                'action' => 'rejected',
                'reason' => $cleanReason,
                'created_at' => now(),
            ]);

            return $pwa->fresh([
                'category:id,name,name_ar,slug',
                'developer:id,first_name,last_name,username',
            ]);
        });
    }
}
