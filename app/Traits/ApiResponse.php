<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        int $code = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('messages.success'),
            'data' => $data,
            'errors' => null,
        ], $code);
    }

    /**
     * Return a standardized 201 Created JSON response.
     */
    protected function createdResponse(
        mixed $data = null,
        ?string $message = null
    ): JsonResponse {
        return $this->successResponse($data, $message ?? __('messages.created'), Response::HTTP_CREATED);
    }

    /**
     * Return a standardized paginated JSON response.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        ?string $message = null
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('messages.success'),
            'data' => $resourceClass::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'errors' => null,
        ]);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function errorResponse(
        ?string $message = null,
        int $code = Response::HTTP_BAD_REQUEST,
        mixed $errors = null,
        mixed $data = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message ?? __('messages.error'),
            'data' => $data,
            'errors' => $errors,
        ], $code);
    }
}
