<?php

namespace Tests\Feature;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\Installation;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $developer;

    protected User $user;

    protected User $moderator;

    protected Category $category;

    protected Pwa $publishedPwa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->developer = User::factory()->create(['username' => 'dev_harden', 'email' => 'dev_harden@example.com']);
        $this->developer->assignRole('developer');

        $this->user = User::factory()->create(['username' => 'user_harden', 'email' => 'user_harden@example.com']);
        $this->user->assignRole('user');

        $this->moderator = User::factory()->create(['username' => 'mod_harden', 'email' => 'mod_harden@example.com']);
        $this->moderator->assignRole('moderator');

        $this->category = Category::factory()->create(['is_active' => true, 'slug' => 'tools']);

        $this->publishedPwa = Pwa::factory()->create([
            'developer_id' => $this->developer->id,
            'category_id' => $this->category->id,
            'name' => 'تطبيق منشور أصلي',
            'slug' => 'published-harden',
            'status' => PwaStatus::Approved,
            'is_published' => true,
        ]);
    }

    public function test_login_with_array_returns_422_not_500(): void
    {
        RateLimiter::clear(request()->ip());

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => ['unexpected_array'],
            'password' => 'password123',
        ], ['Accept-Language' => 'ar']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_route_id_with_non_numeric_or_huge_integer_returns_404_json_on_all_id_endpoints(): void
    {
        Sanctum::actingAs($this->developer, ['*']);

        $badIds = ['abc', '-1', '9999999999999999999999999999'];

        foreach ($badIds as $badId) {
            // Developer routes
            $this->putJson("/api/v1/developer/pwas/{$badId}", ['name' => 'Valid'])
                ->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => __('messages.not_found'),
                    'data' => null,
                    'errors' => null,
                ]);

            $this->postJson("/api/v1/developer/pwas/{$badId}/resubmit")
                ->assertStatus(404)
                ->assertJsonPath('success', false);
        }

        // Moderator routes
        Sanctum::actingAs($this->moderator, ['*']);
        foreach ($badIds as $badId) {
            $this->getJson("/api/v1/moderator/pwas/{$badId}")
                ->assertStatus(404)
                ->assertJsonPath('success', false);

            $this->postJson("/api/v1/moderator/pwas/{$badId}/approve")
                ->assertStatus(404)
                ->assertJsonPath('success', false);

            $this->postJson("/api/v1/moderator/pwas/{$badId}/reject", ['reason' => 'سبب الرفض واضح ومفصل لا يقل عن عشرة'])
                ->assertStatus(404)
                ->assertJsonPath('success', false);
        }

        // Review routes
        Sanctum::actingAs($this->user, ['*']);
        foreach ($badIds as $badId) {
            $this->putJson("/api/v1/reviews/{$badId}", ['rating' => 5])
                ->assertStatus(404)
                ->assertJsonPath('success', false);

            $this->deleteJson("/api/v1/reviews/{$badId}")
                ->assertStatus(404)
                ->assertJsonPath('success', false);
        }
    }

    public function test_pwa_creation_with_soft_deleted_slug_collision_generates_new_slug_and_returns_201(): void
    {
        Sanctum::actingAs($this->developer, ['*']);

        $oldPwa = Pwa::factory()->create([
            'developer_id' => $this->developer->id,
            'category_id' => $this->category->id,
            'name' => 'نفس الاسم المتصادم',
            'slug' => 'nfs-alasm-almtssadm',
            'status' => PwaStatus::Approved,
            'is_published' => true,
        ]);
        $oldPwa->delete();

        $this->assertSoftDeleted('pwas', ['id' => $oldPwa->id]);

        $response = $this->postJson('/api/v1/developer/pwas', [
            'name' => 'نفس الاسم المتصادم',
            'category_id' => $this->category->id,
            'short_description' => 'وصف قصير صالح لتطبيق فريد',
            'manifest_url' => 'https://collision-test.example.com/manifest.json',
            'start_url' => 'https://collision-test.example.com/',
        ], ['Accept-Language' => 'ar']);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $newPwaId = $response->json('data.id');
        $newSlug = $response->json('data.slug');

        $this->assertNotEquals($oldPwa->slug, $newSlug);
        $this->assertDatabaseHas('pwas', [
            'id' => $newPwaId,
            'slug' => $newSlug,
            'deleted_at' => null,
        ]);
    }

    public function test_length_limits_enforced_with_422(): void
    {
        Sanctum::actingAs($this->developer, ['*']);

        $basePayload = [
            'name' => 'اسم تطبيق تجريبي',
            'category_id' => $this->category->id,
            'short_description' => 'وصف قصير صالح',
            'manifest_url' => 'https://limits.example.com/manifest.json',
            'start_url' => 'https://limits.example.com/',
        ];

        // 1. description max:5000 (5001 characters)
        $this->postJson('/api/v1/developer/pwas', array_merge($basePayload, [
            'description' => str_repeat('D', 5001),
        ]), ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        // 2. version max:30 and regex
        $this->postJson('/api/v1/developer/pwas', array_merge($basePayload, [
            'version' => '11111111111.22222222222.33333333333',
        ]), ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['version']);

        // 3. Register password max:72 (73 characters)
        RateLimiter::clear(request()->ip());
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'أحمد',
            'last_name' => 'المحمدي',
            'username' => 'ahmed_harden_len',
            'email' => 'ahmed_len@example.com',
            'password' => str_repeat('P', 73),
            'password_confirmation' => str_repeat('P', 73),
            'role' => 'user',
        ], ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 4. page max:10000 (page=10001)
        $this->getJson('/api/v1/pwas?page=10001')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        $this->getJson('/api/v1/developer/pwas?page=10001')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        $this->getJson('/api/v1/me/installations?page=10001')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        Sanctum::actingAs($this->moderator, ['*']);
        $this->getJson('/api/v1/moderator/pwas/pending?page=10001')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        $this->getJson("/api/v1/pwas/{$this->publishedPwa->slug}/reviews?page=10001")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    public function test_put_developer_pwa_empty_payload_returns_422_with_arabic_message(): void
    {
        Sanctum::actingAs($this->developer, ['*']);

        $pwa = Pwa::factory()->create([
            'developer_id' => $this->developer->id,
            'category_id' => $this->category->id,
            'status' => PwaStatus::Draft,
            'is_published' => false,
        ]);

        $response = $this->putJson("/api/v1/developer/pwas/{$pwa->id}", [], ['Accept-Language' => 'ar']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payload'])
            ->assertJsonFragment(['payload' => ['يجب تقديم حقل واحد على الأقل للتحديث.']]);
    }

    public function test_safe_public_url_rule_blocks_local_private_ips_and_unsafe_protocols(): void
    {
        Sanctum::actingAs($this->developer, ['*']);

        $basePayload = [
            'name' => 'تطبيق أمني للفحص',
            'category_id' => $this->category->id,
            'short_description' => 'وصف للتطبيق',
            'start_url' => 'https://valid-safe-app.example.com/',
        ];

        $unsafeUrls = [
            'https://localhost/m.json',
            'https://127.0.0.1/m.json',
            'https://192.168.1.1/m.json',
            'https://169.254.169.254/m.json',
            'https://[::1]/m.json',
            'https://2130706433/m.json',
            'https://0x7f.0.0.1/m.json',
            'https://user:pass@example.com/m.json',
            'http://example.com/m.json',
            'javascript:alert(1)',
            'data:text/html,test',
            'https://example.com:8443/m.json',
            'https://service.internal/m.json',
        ];

        foreach ($unsafeUrls as $url) {
            $response = $this->postJson('/api/v1/developer/pwas', array_merge($basePayload, [
                'manifest_url' => $url,
            ]), ['Accept-Language' => 'ar']);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['manifest_url']);
        }
    }

    public function test_html_scripts_sanitized_and_not_stored_raw(): void
    {
        Sanctum::actingAs($this->developer, ['*']);

        $response = $this->postJson('/api/v1/developer/pwas', [
            'name' => 'تطبيق <b>مهم</b> <script>alert(1)</script>',
            'category_id' => $this->category->id,
            'short_description' => 'وصف <script>eval("evil")</script> قصير',
            'description' => '<p>وصف كامل</p> <script>alert("xss")</script>',
            'manifest_url' => 'https://sanitized-test.example.com/manifest.json',
            'start_url' => 'https://sanitized-test.example.com/',
        ], ['Accept-Language' => 'ar']);

        $response->assertStatus(201);
        $pwaId = $response->json('data.id');

        $pwa = Pwa::find($pwaId);
        $this->assertStringNotContainsString('<script>', $pwa->name);
        $this->assertStringNotContainsString('<script>', $pwa->short_description);
        $this->assertStringNotContainsString('<script>', $pwa->description);

        // Review sanitization
        Sanctum::actingAs($this->user, ['*']);
        Installation::create(['user_id' => $this->user->id, 'pwa_id' => $this->publishedPwa->id]);

        $reviewResponse = $this->postJson("/api/v1/pwas/{$this->publishedPwa->slug}/reviews", [
            'rating' => 5,
            'title' => 'عنوان <script>alert(1)</script>',
            'content' => 'محتوى <b>عريض</b> <script>steal()</script>',
        ], ['Accept-Language' => 'ar']);

        $reviewResponse->assertStatus(201);
        $review = Review::latest('id')->first();
        $this->assertStringNotContainsString('<script>', $review->title);
        $this->assertStringNotContainsString('<script>', $review->content);

        // Registration with HTML in first_name must fail regex with 422
        RateLimiter::clear(request()->ip());
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'first_name' => '<script>alert(1)</script>',
            'last_name' => 'الغامدي',
            'username' => 'xss_reg_fail',
            'email' => 'xss_reg@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'user',
        ], ['Accept-Language' => 'ar']);

        $regResponse->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_case_insensitive_email_registration_and_login(): void
    {
        RateLimiter::clear(request()->ip());

        $firstReg = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'سعد',
            'last_name' => 'العتيبي',
            'username' => 'Saad_Dev_User',
            'email' => 'Saad_Case@Example.COM',
            'password' => 'SecretPassword123!',
            'password_confirmation' => 'SecretPassword123!',
            'role' => 'user',
        ], ['Accept-Language' => 'ar']);

        $firstReg->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'saad_case@example.com',
            'username' => 'saad_dev_user',
        ]);

        RateLimiter::clear(request()->ip());

        // Second registration with lowercase should fail uniqueness
        $secondReg = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'سعد',
            'last_name' => 'العتيبي',
            'username' => 'saad_dev_user',
            'email' => 'saad_case@example.com',
            'password' => 'SecretPassword123!',
            'password_confirmation' => 'SecretPassword123!',
            'role' => 'user',
        ], ['Accept-Language' => 'ar']);

        $secondReg->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'username']);

        // Login with mixed case must succeed
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'SAAD_CASE@EXAMPLE.COM',
            'password' => 'SecretPassword123!',
        ], ['Accept-Language' => 'ar']);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_arabic_validation_messages_contain_no_default_english_placeholders(): void
    {
        $endpoints = [
            ['POST', '/api/v1/auth/register', ['role' => 'invalid_role']],
            ['POST', '/api/v1/auth/login', ['login' => '']],
            ['GET', '/api/v1/pwas?sort=invalid_sort', []],
            ['GET', '/api/v1/pwas?per_page=999', []],
        ];

        foreach ($endpoints as [$method, $uri, $payload]) {
            $res = $this->json($method, $uri, $payload, ['Accept-Language' => 'ar']);
            $this->assertEquals(422, $res->status());

            $jsonStr = $res->getContent();
            $this->assertStringNotContainsString('The ', $jsonStr, "Endpoint {$uri} contains English placeholder 'The '");
            $this->assertStringNotContainsString(' field ', $jsonStr, "Endpoint {$uri} contains English placeholder ' field '");
        }
    }
}
