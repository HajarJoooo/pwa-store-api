<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\Pwa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstallationService
{
    /**
     * Install a published PWA for a user.
     * Idempotent and safe with concurrent duplicate requests.
     *
     * @return array{is_new: bool, installed_at: Carbon|string, install_count: int}
     */
    public function install(User $user, string $slug): array
    {
        $pwa = Pwa::published()->where('slug', $slug)->first();

        if (! $pwa) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        // Fast-path: Check if user already installed
        $existing = Installation::where('user_id', $user->id)
            ->where('pwa_id', $pwa->id)
            ->first();

        if ($existing) {
            return [
                'is_new' => false,
                'installed_at' => $existing->installed_at,
                'install_count' => (int) $pwa->install_count,
            ];
        }

        try {
            return DB::transaction(function () use ($user, $pwa) {
                $installation = Installation::create([
                    'user_id' => $user->id,
                    'pwa_id' => $pwa->id,
                    'installed_at' => now(),
                ]);

                // Atomic database increment
                Pwa::where('id', $pwa->id)->increment('install_count');

                $updatedCount = (int) Pwa::where('id', $pwa->id)->value('install_count');

                return [
                    'is_new' => true,
                    'installed_at' => $installation->installed_at,
                    'install_count' => $updatedCount,
                ];
            });
        } catch (UniqueConstraintViolationException|QueryException $e) {
            // Concurrent request created the record simultaneously
            $existing = Installation::where('user_id', $user->id)
                ->where('pwa_id', $pwa->id)
                ->first();

            $currentCount = (int) Pwa::where('id', $pwa->id)->value('install_count');

            return [
                'is_new' => false,
                'installed_at' => $existing?->installed_at ?? now(),
                'install_count' => $currentCount,
            ];
        }
    }

    /**
     * Get paginated installations for the authenticated user (published apps only).
     */
    public function getUserInstallations(User $user, int $perPage = 12): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Installation::where('user_id', $user->id)
            ->whereHas('pwa', function ($q): void {
                $q->published();
            })
            ->with([
                'pwa' => function ($q): void {
                    $q->published()
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
                },
            ])
            ->orderByDesc('installed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
