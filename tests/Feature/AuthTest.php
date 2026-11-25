<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_user_can_register_as_regular_user(): void
    {
        $payload = [
            'first_name' => 'خالد',
            'last_name' => 'المنصور',
            'username' => 'khaled_user',
            'email' => 'khaled@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+966555123456',
            'role' => 'user',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'تم إنشاء الحساب بنجاح.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'full_name',
                        'username',
                        'email',
                        'roles',
                        'permissions',
                    ],
                    'token',
                    'token_type',
                ],
                'errors',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'khaled@example.com',
            'username' => 'khaled_user',
            'is_active' => true,
        ]);

        $user = User::where('email', 'khaled@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('developer'));
    }

    public function test_user_can_register_as_developer(): void
    {
        $payload = [
            'first_name' => 'فيصل',
            'last_name' => 'المطور',
            'username' => 'faisal_dev',
            'email' => 'faisal@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'developer',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertCreated();

        $user = User::where('email', 'faisal@example.com')->first();
        $this->assertTrue($user->hasRole('developer'));
        $this->assertTrue($user->can('pwas.submit'));
    }

    public function test_registration_rejects_moderator_and_admin_roles(): void
    {
        $moderatorPayload = [
            'first_name' => 'محمد',
            'last_name' => 'المشرف',
            'username' => 'fake_mod',
            'email' => 'mod@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'moderator',
        ];

        $response = $this->postJson('/api/v1/auth/register', $moderatorPayload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $adminPayload = array_merge($moderatorPayload, [
            'username' => 'fake_admin',
            'email' => 'admin_fake@example.com',
            'role' => 'admin',
        ]);

        $responseAdmin = $this->postJson('/api/v1/auth/register', $adminPayload);
        $responseAdmin->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_registration_fails_with_duplicate_email_or_username(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'username' => 'existing_user',
        ]);

        $payload = [
            'first_name' => 'سالم',
            'last_name' => 'العتيبي',
            'username' => 'existing_user',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'user',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'بيانات الإدخال غير صالحة، يرجى تصحيح الأخطاء.',
            ])
            ->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'login_email@example.com',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => true,
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'login_email@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح.',
            ])
            ->assertJsonPath('data.user.email', 'login_email@example.com')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'username', 'email', 'roles'],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'username' => 'unique_login_name',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => true,
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'unique_login_name',
            'password' => 'SecretPass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.username', 'unique_login_name');
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'valid_user@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'valid_user@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'بيانات الاعتماد المدخلة غير صحيحة.',
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_login_fails_for_inactive_account(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'inactive@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'تم تعطيل هذا الحساب، يرجى التواصل مع إدارة المتجر.',
            ]);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'يجب تسجيل الدخول للوصول إلى هذا المورد.',
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_me_endpoint_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'عمر',
            'last_name' => 'المهندس',
            'is_active' => true,
        ]);
        $user->assignRole('developer');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'full_name' => 'عمر المهندس',
                    'roles' => ['developer'],
                ],
            ])
            ->assertJsonMissing(['password', 'remember_token']);
    }

    public function test_logout_invalidates_current_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test_token')->plainTextToken;

        // Logout with Bearer token
        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم تسجيل الخروج بنجاح.',
            ]);

        // Forget cached in-memory auth state in the test runner
        $this->app['auth']->guard('sanctum')->forgetUser();

        // Attempting to access /me with the deleted token should now be unauthorized
        $subsequentResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $subsequentResponse->assertUnauthorized();
    }

    public function test_login_rate_limiting_returns_429(): void
    {
        $credentials = [
            'login' => 'rate_limit_target@example.com',
            'password' => 'WrongPass!',
        ];

        // Send 5 failed attempts (within the limit)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $credentials);
        }

        // 6th attempt should be blocked
        $blockedResponse = $this->postJson('/api/v1/auth/login', $credentials);

        $blockedResponse->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'تجاوزت الحد المسموح به من المحاولات، يرجى الانتظار قليلاً والمحاولة لاحقاً.',
            ]);
    }

    public function test_accept_language_header_returns_english_when_requested(): void
    {
        $responseEn = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/auth/me');

        $responseEn->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Authentication is required to access this resource.',
            ]);
    }

    public function test_default_response_language_is_arabic(): void
    {
        $responseAr = $this->getJson('/api/v1/auth/me');

        $responseAr->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'يجب تسجيل الدخول للوصول إلى هذا المورد.',
            ]);
    }
}
