<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PwaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Installation;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @group لوحة تحكم الإدارة (Admin Dashboard)
 */
class AdminController extends Controller
{
    use ApiResponse;

    /**
     * Get platform overview statistics.
     */
    public function stats(): JsonResponse
    {
        $totalPwas = Pwa::count();
        $publishedPwas = Pwa::where('status', PwaStatus::Approved)->where('is_published', true)->count();
        $pendingPwas = Pwa::where('status', PwaStatus::Pending)->count();
        $rejectedPwas = Pwa::where('status', PwaStatus::Rejected)->count();
        $draftPwas = Pwa::where('status', PwaStatus::Draft)->count();

        $totalUsers = User::count();
        $totalDevelopers = User::role('developer')->count();
        $verifiedDevelopers = User::role('developer')->where('is_verified', true)->count();

        $totalInstalls = Pwa::sum('install_count');
        $totalReviews = Review::count();

        return $this->successResponse([
            'pwas' => [
                'total' => $totalPwas,
                'published' => $publishedPwas,
                'pending' => $pendingPwas,
                'rejected' => $rejectedPwas,
                'draft' => $draftPwas,
            ],
            'users' => [
                'total' => $totalUsers,
                'developers' => $totalDevelopers,
                'verified_developers' => $verifiedDevelopers,
            ],
            'engagement' => [
                'total_installs' => (int) $totalInstalls,
                'total_reviews' => $totalReviews,
            ],
        ]);
    }

    /**
     * Get users list with optional search and role filter.
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::with(['roles']);

        if ($request->filled('role')) {
            $query->role($request->input('role'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $paginated = $query->latest('id')->paginate($perPage);

        return $this->paginatedResponse($paginated, UserResource::class);
    }

    /**
     * Toggle developer verification status.
     */
    public function toggleUserVerify(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        $user->is_verified = ! $user->is_verified;
        $user->save();

        return $this->successResponse(
            new UserResource($user->load(['roles', 'permissions'])),
            $user->is_verified ? 'تم توثيق هوية المستخدم بنجاح' : 'تم إلغاء توثيق هوية المستخدم'
        );
    }

    /**
     * Toggle user active status.
     */
    public function toggleUserActive(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return $this->successResponse(
            new UserResource($user->load(['roles', 'permissions'])),
            $user->is_active ? 'تم تفعيل الحساب بنجاح' : 'تم تعطيل الحساب'
        );
    }

    /**
     * Add a new category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon_url' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']) ?: 'category-'.time();
        }

        $validated['sort_order'] = (Category::max('sort_order') ?? 0) + 1;
        $validated['is_active'] = true;

        $category = Category::create($validated);

        return $this->createdResponse(
            new CategoryResource($category),
            'تمت إضافة التصنيف الجديد بنجاح'
        );
    }

    /**
     * Update an existing category.
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::find($id);
        if (! $category) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_ar' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'icon_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->fill($validated);
        $category->save();

        return $this->successResponse(
            new CategoryResource($category),
            'تم تحديث التصنيف بنجاح'
        );
    }

    /**
     * Delete a category if it has no PWAs.
     */
    public function destroyCategory(int $id): JsonResponse
    {
        $category = Category::find($id);
        if (! $category) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        if ($category->pwas()->exists()) {
            return $this->errorResponse('لا يمكن حذف تصنيف مرتبط بتطبيقات حالية', 422);
        }

        $category->delete();

        return $this->successResponse(null, 'تم حذف التصنيف بنجاح');
    }

    /**
     * Update a user's primary role.
     */
    public function updateUserRole(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:user,developer,moderator,admin'],
        ]);

        $user->syncRoles([$validated['role']]);

        return $this->successResponse(
            new UserResource($user->fresh(['roles', 'permissions'])),
            'تم تحديث دور المستخدم بنجاح'
        );
    }

    /**
     * List review reports for moderation.
     */
    public function reports(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $query = \App\Models\ReviewReport::with(['review.pwa', 'reporter'])
            ->latest('id');

        if (is_string($status) && in_array($status, ['pending', 'resolved', 'dismissed'], true)) {
            $query->where('status', $status);
        }

        return $this->successResponse($query->limit(50)->get());
    }

    /**
     * Resolve or dismiss a review report.
     */
    public function resolveReport(Request $request, int $id): JsonResponse
    {
        $report = \App\Models\ReviewReport::find($id);
        if (! $report) {
            throw new NotFoundHttpException(__('messages.not_found'));
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:resolved,dismissed'],
        ]);

        $report->status = $validated['status'] ?? 'resolved';
        $report->resolved_by = $request->user()?->id;
        $report->resolved_at = now();
        $report->save();

        return $this->successResponse($report, 'تم تحديث حالة البلاغ بنجاح');
    }
}

