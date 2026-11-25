<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installation\InstallationIndexRequest;
use App\Http\Resources\UserInstallationResource;
use App\Services\InstallationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group التثبيت (Installations)
 *
 * تثبيت التطبيقات التقدمية للمستخدم المصادق، تتبع عدد التثبيتات، واستعراض سجل التطبيقات المثبتة للمستخدم.
 */
class InstallationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InstallationService $installationService
    ) {}

    /**
     * Install a PWA for the authenticated user.
     * Idempotent: 201 on new install, 200 if already installed.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $result = $this->installationService->install($request->user(), $slug);

        $data = [
            'installed_at' => $result['installed_at'],
            'install_count' => $result['install_count'],
        ];

        if ($result['is_new']) {
            return $this->createdResponse($data, __('messages.installed_successfully'));
        }

        return $this->successResponse($data, __('messages.already_installed'));
    }

    /**
     * Get paginated list of installed PWAs for the authenticated user.
     */
    public function index(InstallationIndexRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 12);
        $paginated = $this->installationService->getUserInstallations($request->user(), $perPage);

        return $this->paginatedResponse($paginated, UserInstallationResource::class);
    }
}
