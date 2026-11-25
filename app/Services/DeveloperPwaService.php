<?php

namespace App\Services;

use App\Enums\PwaStatus;
use App\Models\Pwa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeveloperPwaService
{
    /**
     * Escape special characters (% and _ and \) for SQL LIKE expressions.
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Get paginated PWAs owned by the authenticated developer.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getDeveloperPwas(User $developer, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        $query = Pwa::where('developer_id', $developer->id)
            ->with(['category:id,name,name_ar,slug']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

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

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Submit a new PWA for review.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createPwa(User $developer, array $data): Pwa
    {
        // Check manifest_url uniqueness for this developer
        if (Pwa::where('developer_id', $developer->id)->where('manifest_url', $data['manifest_url'])->exists()) {
            throw ValidationException::withMessages([
                'manifest_url' => [__('messages.pwa_manifest_exists')],
            ]);
        }

        // Check max pending PWAs limit
        $maxPending = config('developer.max_pending', 10);
        $pendingCount = Pwa::where('developer_id', $developer->id)
            ->where('status', PwaStatus::Pending)
            ->count();

        if ($pendingCount >= $maxPending) {
            throw ValidationException::withMessages([
                'developer' => [__('messages.max_pending_pwas_reached', ['max' => $maxPending])],
            ]);
        }

        $pwa = new Pwa;
        $pwa->developer_id = $developer->id;
        $pwa->category_id = (int) $data['category_id'];
        $pwa->name = $data['name'];
        $pwa->short_description = $data['short_description'];
        $pwa->description = $data['description'] ?? null;
        $pwa->manifest_url = $data['manifest_url'];
        $pwa->start_url = $data['start_url'];
        $pwa->icon_url = $data['icon_url'] ?? null;
        $pwa->screenshots = $data['screenshots'] ?? null;
        $pwa->version = $data['version'] ?? '1.0.0';

        // Server-enforced security fields
        $pwa->status = PwaStatus::Pending;
        $pwa->is_published = false;
        $pwa->install_count = 0;
        $pwa->rating_average = 0.00;
        $pwa->review_count = 0;
        $pwa->rejection_reason = null;
        $pwa->reviewed_by = null;
        $pwa->reviewed_at = null;

        $pwa->save();

        return $pwa->load(['category:id,name,name_ar,slug']);
    }

    /**
     * Update an existing PWA owned by the developer (draft or rejected only).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    public function updatePwa(User $developer, int $id, array $data): Pwa
    {
        $pwa = Pwa::where('developer_id', $developer->id)
            ->where('id', $id)
            ->first();

        if (! $pwa) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        if ($pwa->status === PwaStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [__('messages.cannot_update_approved_pwa')],
            ]);
        }

        if ($pwa->status === PwaStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => [__('messages.cannot_update_pending_pwa')],
            ]);
        }

        if (array_key_exists('manifest_url', $data) && $data['manifest_url'] !== $pwa->manifest_url) {
            $exists = Pwa::where('developer_id', $developer->id)
                ->where('manifest_url', $data['manifest_url'])
                ->where('id', '!=', $pwa->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'manifest_url' => [__('messages.pwa_manifest_exists')],
                ]);
            }
        }

        // Apply only allowed fields (slug, status, rejection_reason are strictly unchanged)
        $allowedFields = [
            'name',
            'category_id',
            'short_description',
            'description',
            'manifest_url',
            'start_url',
            'icon_url',
            'screenshots',
            'version',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $pwa->{$field} = $data[$field];
            }
        }

        $pwa->save();

        return $pwa->fresh(['category:id,name,name_ar,slug']);
    }

    /**
     * Resubmit a rejected or draft PWA for review.
     *
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    public function resubmitPwa(User $developer, int $id): Pwa
    {
        return DB::transaction(function () use ($developer, $id): Pwa {
            $pwa = Pwa::where('developer_id', $developer->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (! $pwa) {
                throw new NotFoundHttpException(__('messages.not_found'));
            }

            if (! in_array($pwa->status, [PwaStatus::Rejected, PwaStatus::Draft], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.cannot_resubmit_pwa')],
                ]);
            }

            $maxPending = config('developer.max_pending', 10);
            $pendingCount = Pwa::where('developer_id', $developer->id)
                ->where('status', PwaStatus::Pending)
                ->count();

            if ($pendingCount >= $maxPending) {
                throw ValidationException::withMessages([
                    'developer' => [__('messages.max_pending_pwas_reached', ['max' => $maxPending])],
                ]);
            }

            $pwa->forceFill([
                'status' => PwaStatus::Pending,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            return $pwa->fresh(['category:id,name,name_ar,slug']);
        });
    }
}
