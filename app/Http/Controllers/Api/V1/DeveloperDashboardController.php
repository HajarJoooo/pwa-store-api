<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeveloperDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group لوحة تحكم المطور (Developer Dashboard)
 *
 * استعراض الإحصائيات الشاملة لتطبيقات المطور (إجمالي التطبيقات، التثبيتات، متوسط التقييم، أحدث المراجعات، وأعلى التطبيقات أداءً).
 */
class DeveloperDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DeveloperDashboardService $dashboardService
    ) {}

    /**
     * Get aggregate metrics and top items for the developer dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboardData($request->user());

        return $this->successResponse(
            $data,
            __('messages.dashboard_retrieved_successfully')
        );
    }
}
