<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReviewStoreRequest;
use App\Http\Requests\Review\ReviewUpdateRequest;
use App\Http\Resources\ReviewResource;
use App\Services\ReviewService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group المراجعات والتقييمات (Reviews)
 *
 * إضافة تقييم ومراجعة للتطبيقات المنشورة (مراجعة واحدة لكل مستخدم)، وتحديث أو حذف المراجعة الخاصة بالمستخدم.
 */
class ReviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReviewService $reviewService
    ) {}

    /**
     * Submit a review for a PWA.
     */
    public function store(ReviewStoreRequest $request, string $slug): JsonResponse
    {
        $review = $this->reviewService->createReview($request->user(), $slug, $request->validated());

        return $this->createdResponse(new ReviewResource($review), __('messages.review_added_successfully'));
    }

    /**
     * Update an existing review owned by the authenticated user.
     */
    public function update(ReviewUpdateRequest $request, int $id): JsonResponse
    {
        $review = $this->reviewService->updateReview($request->user(), $id, $request->validated());

        return $this->successResponse(new ReviewResource($review), __('messages.review_updated_successfully'));
    }

    /**
     * Soft delete a review owned by the authenticated user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->reviewService->deleteReview($request->user(), $id);

        return $this->successResponse(null, __('messages.review_deleted_successfully'));
    }

    /**
     * Report an abusive or inappropriate review.
     */
    public function report(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = \App\Models\Review::find($id);
        if (! $review) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(__('messages.not_found'));
        }

        \App\Models\ReviewReport::create([
            'review_id' => $review->id,
            'user_id' => $request->user()?->id,
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'status' => 'pending',
        ]);

        \Illuminate\Support\Facades\Log::info("Review ID {$id} reported by user " . ($request->user()?->id ?? 'anonymous') . ": " . $request->input('reason'));

        return $this->successResponse(null, 'تم استلام بلاغك بنجاح، وسيقوم فريق الإشراف بمراجعة هذا المحتوى.');
    }
}
