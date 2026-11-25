<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pwa\PwaIndexRequest;
use App\Http\Requests\Review\ReviewIndexRequest;
use App\Http\Resources\PwaListResource;
use App\Http\Resources\PwaResource;
use App\Http\Resources\ReviewResource;
use App\Services\PwaService;
use Illuminate\Http\JsonResponse;

/**
 * @group التطبيقات العامة (Public PWAs)
 *
 * تصفح تطبيقات الويب التقدمية المنشورة، البحث، التصفية حسب التصنيف، وعرض التفاصيل والمراجعات دون حاجة لتسجيل الدخول.
 */
class PwaController extends Controller
{
    /**
     * Browse and search published PWAs with pagination and filters.
     */
    public function index(PwaIndexRequest $request, PwaService $service): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 12);
        $paginator = $service->getPublishedPwas($request->validated(), $perPage);

        return $this->paginatedResponse($paginator, PwaListResource::class);
    }

    /**
     * Display published PWA details by slug.
     */
    public function show(string $slug, PwaService $service): JsonResponse
    {
        $pwa = $service->getPublishedPwaBySlug($slug);

        return $this->successResponse(new PwaResource($pwa));
    }

    /**
     * List approved reviews for a published PWA.
     */
    public function reviews(string $slug, ReviewIndexRequest $request, PwaService $service): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $paginator = $service->getPwaReviews($slug, $request->validated(), $perPage);

        return $this->paginatedResponse($paginator, ReviewResource::class);
    }
}
