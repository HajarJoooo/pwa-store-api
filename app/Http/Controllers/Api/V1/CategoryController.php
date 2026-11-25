<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

/**
 * @group التصنيفات (Categories)
 *
 * استرجاع شجرة التصنيفات النشطة مع عدد التطبيقات المنشورة لكل تصنيف (مخزنة في الكاش).
 */
class CategoryController extends Controller
{
    /**
     * List active categories tree with published PWAs count.
     */
    public function index(CategoryService $service): JsonResponse
    {
        $categories = $service->getActiveCategoriesTree();

        return $this->successResponse(CategoryResource::collection($categories));
    }
}
