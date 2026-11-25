<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group المصادقة (Authentication)
 *
 * نقاط نهاية إدارة المصادقة، تسجيل المستخدمين والمطورين، تسجيل الدخول، واسترجاع بيانات الملف الشخصي ورموز الوصول.
 */
class AuthController extends Controller
{
    /**
     * Register a new user with user or developer role.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = $validated['role'];
        unset($validated['role']);

        $user = new User($validated);
        $user->is_active = true;
        $user->is_verified = false;
        $user->save();
        $user->assignRole($role);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => new UserResource($user->load(['roles', 'permissions'])),
            'token' => $token,
            'token_type' => 'Bearer',
        ], __('messages.registered_successfully'), Response::HTTP_CREATED);
    }

    /**
     * Authenticate user by email or username.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->validated('login');
        $password = $request->validated('password');

        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return $this->errorResponse(
                __('messages.invalid_credentials'),
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (! $user->is_active) {
            return $this->errorResponse(
                __('messages.account_inactive'),
                Response::HTTP_FORBIDDEN
            );
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => new UserResource($user->load(['roles', 'permissions'])),
            'token' => $token,
            'token_type' => 'Bearer',
        ], __('messages.logged_in_successfully'));
    }

    /**
     * Return authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            new UserResource($user->load(['roles', 'permissions']))
        );
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            null,
            __('messages.logged_out_successfully')
        );
    }

    /**
     * Upgrade an existing user to developer role.
     */
    public function becomeDeveloper(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasRole('developer') && ! $user->hasRole('admin')) {
            $user->assignRole('developer');
        }

        return $this->successResponse(
            new UserResource($user->fresh(['roles', 'permissions'])),
            'تهانينا! تمت ترقية حسابك إلى حساب مطور بنجاح. يمكنك الآن إضافة تطبيقاتك وإدارتها.'
        );
    }

    /**
     * Update authenticated user profile fields.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'starts_with:https://', 'max:500'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $user->fill($validated);
        $user->save();

        return $this->successResponse(
            new UserResource($user->fresh(['roles', 'permissions'])),
            'تم تحديث الملف الشخصي بنجاح'
        );
    }
}
