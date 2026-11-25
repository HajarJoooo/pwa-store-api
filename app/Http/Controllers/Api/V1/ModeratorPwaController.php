<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Moderator\ModeratorPendingPwaIndexRequest;
use App\Http\Requests\Moderator\ModeratorRejectRequest;
use App\Http\Resources\ModeratorPwaResource;
use App\Services\ModerationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group بوابة المشرف (Moderator Portal)
 *
 * استعراض قائمة التطبيقات المعلقة بنظام الأقدم أولاً (FIFO)، فحص بيانات التطبيق والمطور، والموافقة على النشر أو الرفض مع سبب مطهر.
 */
class ModeratorPwaController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ModerationService $moderationService
    ) {}

    /**
     * Get pending PWAs for review in FIFO order.
     */
    public function pending(ModeratorPendingPwaIndexRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 12);
        $paginated = $this->moderationService->getPendingPwas($request->validated(), $perPage);

        return $this->paginatedResponse($paginated, ModeratorPwaResource::class);
    }

    /**
     * Get details of a PWA for moderator review.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $pwa = $this->moderationService->getPwaForModerator($id);

        return $this->successResponse(new ModeratorPwaResource($pwa));
    }

    /**
     * Approve a pending PWA and publish it.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $pwa = $this->moderationService->approvePwa($request->user(), $id);

        return $this->successResponse(
            new ModeratorPwaResource($pwa),
            __('messages.pwa_approved_successfully')
        );
    }

    /**
     * Reject a pending PWA with reason.
     */
    public function reject(ModeratorRejectRequest $request, int $id): JsonResponse
    {
        $pwa = $this->moderationService->rejectPwa(
            $request->user(),
            $id,
            $request->validated('reason')
        );

        return $this->successResponse(
            new ModeratorPwaResource($pwa),
            __('messages.pwa_rejected_successfully')
        );
    }
}
