<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AiDiscoveryController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DeveloperDashboardController;
use App\Http\Controllers\Api\V1\DeveloperPwaController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\InstallationController;
use App\Http\Controllers\Api\V1\ModeratorPwaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PwaController;
use App\Http\Controllers\Api\V1\ReviewController;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]{1,18}');

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    // Auth routes
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->withoutMiddleware('throttle:api')
            ->middleware('throttle:register');

        Route::post('login', [AuthController::class, 'login'])
            ->withoutMiddleware('throttle:api')
            ->middleware('throttle:login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('become-developer', [AuthController::class, 'becomeDeveloper']);
        });
    });

    // Public routes (No authentication required)
    Route::get('pwas', [PwaController::class, 'index']);
    Route::get('pwas/{slug}', [PwaController::class, 'show']);
    Route::get('pwas/{slug}/reviews', [PwaController::class, 'reviews']);

    Route::get('categories', [CategoryController::class, 'index']);

    // AI Discovery & Analytics
    Route::post('ai/discover', [AiDiscoveryController::class, 'discover']);
    Route::post('analytics/events', [AnalyticsController::class, 'logEvent']);

    // Authenticated User routes (Installations, Favorites, Reviews, Notifications)
    Route::middleware('auth:sanctum')->group(function (): void {
        // User installations
        Route::get('me/installations', [InstallationController::class, 'index']);

        // User favorites
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::get('favorites/ids', [FavoriteController::class, 'ids']);
        Route::post('favorites/{id}', [FavoriteController::class, 'toggle']);
        Route::delete('favorites/{id}', [FavoriteController::class, 'destroy']);
        Route::post('pwas/{slug}/favorite', [FavoriteController::class, 'toggle']);

        // User notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

        // PWA analytics details (developer or admin)
        Route::get('analytics/pwa/{id}', [AnalyticsController::class, 'pwaStats']);

        // Write routes with write throttle & Spatie permissions
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('pwas/{slug}/install', [InstallationController::class, 'store'])
                ->middleware('permission:pwas.install');

            Route::post('pwas/{slug}/reviews', [ReviewController::class, 'store'])
                ->middleware('permission:reviews.create');

            Route::match(['put', 'patch'], 'reviews/{id}', [ReviewController::class, 'update'])
                ->middleware('permission:reviews.update-own');

            Route::delete('reviews/{id}', [ReviewController::class, 'destroy'])
                ->middleware('permission:reviews.delete-own');

            Route::post('reviews/{id}/report', [ReviewController::class, 'report']);
        });
    });

    // Developer routes (Prefix: /api/v1/developer)
    Route::prefix('developer')->middleware('auth:sanctum')->group(function (): void {
        // Dashboard
        Route::get('dashboard', [DeveloperDashboardController::class, 'index'])
            ->middleware('permission:developer.dashboard');

        // Developer PWAs list
        Route::get('pwas', [DeveloperPwaController::class, 'index'])
            ->middleware('permission:developer.dashboard');

        // Developer write routes with throttle:write
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('pwas', [DeveloperPwaController::class, 'store'])
                ->middleware('permission:pwas.submit');

            Route::match(['put', 'patch'], 'pwas/{id}', [DeveloperPwaController::class, 'update'])
                ->middleware('permission:pwas.update-own');

            Route::post('pwas/{id}/resubmit', [DeveloperPwaController::class, 'resubmit'])
                ->middleware('permission:pwas.submit');
        });
    });

    // Moderator routes (Prefix: /api/v1/moderator)
    Route::prefix('moderator')->middleware('auth:sanctum')->group(function (): void {
        // Pending PWAs list
        Route::get('pwas/pending', [ModeratorPwaController::class, 'pending'])
            ->middleware('permission:pwas.view-pending');

        // PWA details for moderation
        Route::get('pwas/{id}', [ModeratorPwaController::class, 'show'])
            ->middleware('permission:pwas.view-pending');

        // Review decision routes with throttle:write
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('pwas/{id}/approve', [ModeratorPwaController::class, 'approve'])
                ->middleware('permission:pwas.review');

            Route::post('pwas/{id}/reject', [ModeratorPwaController::class, 'reject'])
                ->middleware('permission:pwas.review');
        });
    });

    // Admin routes (Prefix: /api/v1/admin)
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
        Route::get('stats', [AdminController::class, 'stats']);
        Route::get('users', [AdminController::class, 'users']);
        Route::post('users/{id}/toggle-verify', [AdminController::class, 'toggleUserVerify']);
        Route::post('users/{id}/toggle-active', [AdminController::class, 'toggleUserActive']);
        Route::post('users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::post('categories', [AdminController::class, 'storeCategory']);
        Route::put('categories/{id}', [AdminController::class, 'updateCategory']);
        Route::delete('categories/{id}', [AdminController::class, 'destroyCategory']);
        Route::get('reports', [AdminController::class, 'reports']);
        Route::post('reports/{id}/resolve', [AdminController::class, 'resolveReport']);
    });
});
