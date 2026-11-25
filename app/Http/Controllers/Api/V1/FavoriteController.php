<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PwaResource;
use App\Models\Favorite;
use App\Models\Pwa;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group المفضلة (Favorites)
 */
class FavoriteController extends Controller
{
    use ApiResponse;

    /**
     * Get paginated list of user's favorite PWAs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->input('per_page', 12), 1), 50);

        $pwas = Pwa::published()
            ->whereHas('favorites', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['category', 'developer'])
            ->latest('id')
            ->paginate($perPage);

        return $this->paginatedResponse($pwas, PwaResource::class);
    }

    /**
     * Get list of favorite PWA IDs for fast synchronization.
     */
    public function ids(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = Favorite::where('user_id', $user->id)->pluck('pwa_id')->toArray();

        return $this->successResponse($ids);
    }

    /**
     * Toggle favorite status for a PWA.
     */
    public function toggle(Request $request, int|string $idOrSlug): JsonResponse
    {
        $user = $request->user();
        $pwa = is_numeric($idOrSlug)
            ? Pwa::find($idOrSlug)
            : Pwa::where('slug', $idOrSlug)->first();

        if (! $pwa) {
            return $this->errorResponse('التطبيق غير موجود', 404);
        }

        $existing = Favorite::where('user_id', $user->id)
            ->where('pwa_id', $pwa->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return $this->successResponse([
                'pwa_id' => $pwa->id,
                'is_favorite' => false,
            ], 'تمت إزالة التطبيق من المفضلة');
        }

        Favorite::create([
            'user_id' => $user->id,
            'pwa_id' => $pwa->id,
        ]);

        return $this->successResponse([
            'pwa_id' => $pwa->id,
            'is_favorite' => true,
        ], 'تمت إضافة التطبيق إلى المفضلة');
    }

    /**
     * Remove a PWA from favorites.
     */
    public function destroy(Request $request, int|string $idOrSlug): JsonResponse
    {
        $user = $request->user();
        $pwa = is_numeric($idOrSlug)
            ? Pwa::find($idOrSlug)
            : Pwa::where('slug', $idOrSlug)->first();

        if ($pwa) {
            Favorite::where('user_id', $user->id)
                ->where('pwa_id', $pwa->id)
                ->delete();
        }

        return $this->successResponse(null, 'تمت إزالة التطبيق من المفضلة');
    }
}

