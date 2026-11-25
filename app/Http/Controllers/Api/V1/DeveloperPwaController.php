<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\DeveloperPwaIndexRequest;
use App\Http\Requests\Developer\DeveloperPwaStoreRequest;
use App\Http\Requests\Developer\DeveloperPwaUpdateRequest;
use App\Http\Resources\DeveloperPwaResource;
use App\Services\DeveloperPwaService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group إدارة تطبيقات المطور (Developer PWAs)
 *
 * إضافة تطبيقات جديدة، تعديل بيانات التطبيقات المملوكة للمطور، وإعادة تقديم التطبيقات المرفوضة بعد المعالجة.
 */
class DeveloperPwaController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DeveloperPwaService $pwaService
    ) {}

    /**
     * Get paginated list of PWAs owned by the authenticated developer.
     */
    public function index(DeveloperPwaIndexRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 12);
        $paginated = $this->pwaService->getDeveloperPwas(
            $request->user(),
            $request->validated(),
            $perPage
        );

        return $this->paginatedResponse($paginated, DeveloperPwaResource::class);
    }

    /**
     * Submit a new PWA for review.
     */
    public function store(DeveloperPwaStoreRequest $request): JsonResponse
    {
        $pwa = $this->pwaService->createPwa($request->user(), $request->validated());

        return $this->createdResponse(
            new DeveloperPwaResource($pwa),
            __('messages.pwa_submitted_successfully')
        );
    }

    /**
     * Update an existing PWA (draft or rejected only).
     */
    public function update(DeveloperPwaUpdateRequest $request, int $id): JsonResponse
    {
        $pwa = $this->pwaService->updatePwa($request->user(), $id, $request->validated());

        return $this->successResponse(
            new DeveloperPwaResource($pwa),
            __('messages.pwa_updated_successfully')
        );
    }

    /**
     * Resubmit a rejected or draft PWA for review.
     */
    public function resubmit(Request $request, int $id): JsonResponse
    {
        $pwa = $this->pwaService->resubmitPwa($request->user(), $id);

        return $this->successResponse(
            new DeveloperPwaResource($pwa),
            __('messages.pwa_resubmitted_successfully')
        );
    }
}
