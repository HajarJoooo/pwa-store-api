<?php

namespace Tests\Feature;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use App\Services\DeveloperDashboardService;
use App\Services\DeveloperPwaService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeveloperApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function createDeveloper(): User
    {
        $dev = User::factory()->create();
        $dev->assignRole('developer');
        Sanctum::actingAs($dev, ['*']);

        return $dev;
    }

    protected function createActiveCategory(): Category
    {
        return Category::factory()->create([
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. Authorization Tests
    // =========================================================================

    public function test_authorization_checks_for_unauthenticated_user_role_and_moderator(): void
    {
        $cat = $this->createActiveCategory();

        $routes = [
            ['GET', '/api/v1/developer/dashboard', []],
            ['GET', '/api/v1/developer/pwas', []],
            ['POST', '/api/v1/developer/pwas', ['name' => 'تطبيق تجريبي', 'category_id' => $cat->id, 'short_description' => 'وصف', 'manifest_url' => 'https://example.com/manifest.json', 'start_url' => 'https://example.com/']],
            ['PUT', '/api/v1/developer/pwas/1', ['name' => 'تعديل']],
            ['POST', '/api/v1/developer/pwas/1/resubmit', []],
        ];

        // 1. Unauthenticated (no token) -> 401
        foreach ($routes as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertUnauthorized();
        }

        // 2. Regular User role -> 403 Forbidden
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user, ['*']);

        foreach ($routes as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertForbidden();
        }

        // 3. Moderator role -> 403 Forbidden
        $mod = User::factory()->create();
        $mod->assignRole('moderator');
        Sanctum::actingAs($mod, ['*']);

        foreach ($routes as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertForbidden();
        }
    }

    // =========================================================================
    // 2. Isolation Tests (Developer A cannot see/modify Developer B's PWAs)
    // =========================================================================

    public function test_developer_isolation_returns_404_when_accessing_another_developers_pwa(): void
    {
        $devA = $this->createDeveloper();
        $devB = User::factory()->create();
        $devB->assignRole('developer');
        $cat = $this->createActiveCategory();

        $pwaOfB = Pwa::factory()->create([
            'developer_id' => $devB->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Rejected,
            'name' => 'تطبيق المطور ب',
        ]);

        // Developer A lists PWAs: should not see Developer B's PWA
        $listResponse = $this->getJson('/api/v1/developer/pwas');
        $listResponse->assertOk()->assertJsonCount(0, 'data');

        // Developer A attempts to update Developer B's PWA -> 404 (not 403)
        $this->putJson("/api/v1/developer/pwas/{$pwaOfB->id}", ['name' => 'محاولة اختراق'])
            ->assertNotFound();

        // Developer A attempts to resubmit Developer B's PWA -> 404 (not 403)
        $this->postJson("/api/v1/developer/pwas/{$pwaOfB->id}/resubmit")
            ->assertNotFound();
    }

    // =========================================================================
    // 3. Creation Tests
    // =========================================================================

    public function test_developer_can_create_pwa_with_pending_status_and_auto_generated_slug(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        $payload = [
            'name' => 'منصة تفاعلية مميزة',
            'category_id' => $cat->id,
            'short_description' => 'تطبيق ويب تقدمي لإدارة الأعمال وتنظيم المهام',
            'description' => 'وصف كامل ومفصل لكافة الميزات التقنية.',
            'manifest_url' => 'https://app.example.com/manifest.json',
            'start_url' => 'https://app.example.com/',
            'icon_url' => 'https://app.example.com/icon.png',
            'screenshots' => [
                'https://app.example.com/shot1.png',
                'https://app.example.com/shot2.png',
            ],
            'version' => '1.0.0',
        ];

        $response = $this->postJson('/api/v1/developer/pwas', $payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'تم تقديم التطبيق للمراجعة بنجاح.',
                'data' => [
                    'name' => 'منصة تفاعلية مميزة',
                    'status' => 'pending',
                    'is_published' => false,
                    'install_count' => 0,
                    'rating_average' => 0.00,
                    'review_count' => 0,
                    'rejection_reason' => null,
                    'reviewed_at' => null,
                ],
            ]);

        $createdSlug = $response->json('data.slug');
        $this->assertNotEmpty($createdSlug);

        $this->assertDatabaseHas('pwas', [
            'developer_id' => $dev->id,
            'name' => 'منصة تفاعلية مميزة',
            'status' => PwaStatus::Pending->value,
            'is_published' => false,
        ]);

        // Must NOT appear in public list
        $publicList = $this->getJson('/api/v1/pwas');
        $publicList->assertOk()
            ->assertJsonMissing(['slug' => $createdSlug]);
    }

    public function test_pwa_creation_fails_for_non_https_urls(): void
    {
        $this->createDeveloper();
        $cat = $this->createActiveCategory();

        $payload = [
            'name' => 'تطبيق برابط غير آمن',
            'category_id' => $cat->id,
            'short_description' => 'وصف قصير',
            'manifest_url' => 'http://insecure.com/manifest.json', // Not https
            'start_url' => 'https://secure.com/',
        ];

        $this->postJson('/api/v1/developer/pwas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manifest_url']);

        $payload['manifest_url'] = 'https://secure.com/manifest.json';
        $payload['start_url'] = 'http://insecure.com/'; // Not https

        $this->postJson('/api/v1/developer/pwas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_url']);
    }

    public function test_pwa_creation_fails_for_inactive_or_nonexistent_category(): void
    {
        $this->createDeveloper();

        $inactiveCat = Category::factory()->create(['is_active' => false]);

        $payload = [
            'name' => 'تطبيق بتصنيف معطل',
            'category_id' => $inactiveCat->id,
            'short_description' => 'وصف',
            'manifest_url' => 'https://app.com/manifest.json',
            'start_url' => 'https://app.com/',
        ];

        // Inactive category
        $this->postJson('/api/v1/developer/pwas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);

        // Nonexistent category
        $payload['category_id'] = 99999;
        $this->postJson('/api/v1/developer/pwas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_pwa_creation_fails_for_duplicate_manifest_url_for_same_developer(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        $manifestUrl = 'https://app.example.com/unique-manifest.json';

        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'manifest_url' => $manifestUrl,
        ]);

        $payload = [
            'name' => 'تطبيق ثانٍ بنفس الرابط',
            'category_id' => $cat->id,
            'short_description' => 'وصف قصير',
            'manifest_url' => $manifestUrl,
            'start_url' => 'https://app.example.com/',
        ];

        $response = $this->postJson('/api/v1/developer/pwas', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['manifest_url'])
            ->assertJsonPath('errors.manifest_url.0', 'لديك تطبيق مسجل بالفعل بهذا الرابط (Manifest URL).');
    }

    public function test_pwa_creation_enforces_max_pending_limit(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        config(['developer.max_pending' => 2]);

        // Create 2 pending PWAs
        Pwa::factory()->count(2)->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Pending,
        ]);

        $payload = [
            'name' => 'تطبيق يتجاوز الحد',
            'category_id' => $cat->id,
            'short_description' => 'وصف قصير',
            'manifest_url' => 'https://app3.example.com/manifest.json',
            'start_url' => 'https://app3.example.com/',
        ];

        $response = $this->postJson('/api/v1/developer/pwas', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['developer']);
    }

    public function test_mass_assignment_protection_ignores_forbidden_attributes(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        $anotherUser = User::factory()->create();

        $payload = [
            'name' => 'تطبيق آمن',
            'category_id' => $cat->id,
            'short_description' => 'وصف نظيف',
            'manifest_url' => 'https://safe.example.com/manifest.json',
            'start_url' => 'https://safe.example.com/',
            // Maliciously injected fields:
            'developer_id' => $anotherUser->id,
            'status' => 'approved',
            'is_published' => true,
            'install_count' => 9999,
            'rating_average' => 5.00,
            'review_count' => 500,
            'rejection_reason' => 'تم الرفض زوراً',
            'reviewed_by' => $anotherUser->id,
            'reviewed_at' => now()->toIso8601String(),
            'slug' => 'hacked-custom-slug',
        ];

        $response = $this->postJson('/api/v1/developer/pwas', $payload);

        $response->assertCreated();

        $pwa = Pwa::where('name', 'تطبيق آمن')->first();
        $this->assertNotNull($pwa);

        $this->assertSame($dev->id, $pwa->developer_id);
        $this->assertSame(PwaStatus::Pending, $pwa->status);
        $this->assertFalse((bool) $pwa->is_published);
        $this->assertSame(0, (int) $pwa->install_count);
        $this->assertSame(0.00, (float) $pwa->rating_average);
        $this->assertSame(0, (int) $pwa->review_count);
        $this->assertNull($pwa->rejection_reason);
        $this->assertNull($pwa->reviewed_by);
        $this->assertNotSame('hacked-custom-slug', $pwa->slug);
    }

    // =========================================================================
    // 4. Update Tests
    // =========================================================================

    public function test_pwa_update_works_for_draft_and_rejected_statuses_only(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        // 1. Rejected PWA -> update succeeds, slug unchanged, rejection_reason preserved
        $rejectedPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Rejected,
            'slug' => 'rejected-original-slug',
            'rejection_reason' => 'الأيقونة غير واضحة',
        ]);

        $updateResponse = $this->putJson("/api/v1/developer/pwas/{$rejectedPwa->id}", [
            'name' => 'اسم معدل بعد الرفض',
            'slug' => 'trying-to-change-slug',
        ]);

        $updateResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $rejectedPwa->id,
                    'name' => 'اسم معدل بعد الرفض',
                    'slug' => 'rejected-original-slug', // slug stays strictly unchanged!
                    'status' => 'rejected',
                    'rejection_reason' => 'الأيقونة غير واضحة',
                ],
            ]);

        // 2. Draft PWA -> update succeeds
        $draftPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Draft,
        ]);

        $this->putJson("/api/v1/developer/pwas/{$draftPwa->id}", [
            'name' => 'مسودة معدلة',
        ])->assertOk();

        // 3. Pending PWA -> update fails with 422
        $pendingPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Pending,
        ]);

        $this->putJson("/api/v1/developer/pwas/{$pendingPwa->id}", ['name' => 'تعديل المعلق'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        // 4. Approved PWA -> update fails with 422
        $approvedPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Approved,
        ]);

        $this->putJson("/api/v1/developer/pwas/{$approvedPwa->id}", ['name' => 'تعديل المعتمد'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // =========================================================================
    // 5. Resubmit Tests
    // =========================================================================

    public function test_pwa_resubmit_transitions_rejected_and_draft_to_pending_and_clears_review_info(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();
        $admin = User::factory()->create();

        // Rejected PWA with review info
        $rejectedPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Rejected,
            'rejection_reason' => 'محتوى غير كافٍ',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/developer/pwas/{$rejectedPwa->id}/resubmit");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $rejectedPwa->id,
                    'status' => 'pending',
                    'rejection_reason' => null,
                    'reviewed_at' => null,
                ],
            ]);

        $fresh = $rejectedPwa->fresh();
        $this->assertSame(PwaStatus::Pending, $fresh->status);
        $this->assertNull($fresh->rejection_reason);
        $this->assertNull($fresh->reviewed_by);
        $this->assertNull($fresh->reviewed_at);

        // Draft PWA resubmit -> succeeds
        $draftPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Draft,
        ]);

        $this->postJson("/api/v1/developer/pwas/{$draftPwa->id}/resubmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        // Pending or Approved PWA resubmit -> 422
        $approvedPwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Approved,
        ]);

        $this->postJson("/api/v1/developer/pwas/{$approvedPwa->id}/resubmit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // =========================================================================
    // 6. List, Filter and Search Tests
    // =========================================================================

    public function test_developer_pwas_list_supports_filtering_search_and_pagination(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق المالية والمحاسبة',
            'status' => PwaStatus::Approved,
        ]);
        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق إدارة المخزون',
            'status' => PwaStatus::Rejected,
            'rejection_reason' => 'روابط معطلة',
        ]);
        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق المهام اليومية',
            'status' => PwaStatus::Draft,
        ]);

        // 1. All PWAs
        $this->getJson('/api/v1/developer/pwas')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // 2. Filter by status: rejected
        $rejectedResponse = $this->getJson('/api/v1/developer/pwas?status=rejected');
        $rejectedResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.rejection_reason', 'روابط معطلة');

        // 3. Search by name with wildcards
        $searchResponse = $this->getJson('/api/v1/developer/pwas?search=المالية');
        $searchResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'تطبيق المالية والمحاسبة');

        // 4. Invalid status -> 422
        $this->getJson('/api/v1/developer/pwas?status=unknown_status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_developer_pwas_list_query_count_is_minimal(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        for ($i = 0; $i < 10; $i++) {
            Pwa::factory()->create([
                'developer_id' => $dev->id,
                'category_id' => $cat->id,
            ]);
        }

        // Test Service query count directly: 1 count, 1 select pwas, 1 eager load categories
        DB::flushQueryLog();
        DB::enableQueryLog();

        $serviceResult = app(DeveloperPwaService::class)->getDeveloperPwas($dev, [], 10);

        $serviceQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, $serviceQueryCount, "Detected N+1 queries in service logic. Count: {$serviceQueryCount}");

        // Test HTTP endpoint
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/developer/pwas?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $httpQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 3 service queries + Spatie permission middleware queries <= 7
        $this->assertLessThanOrEqual(7, $httpQueryCount, "HTTP query count excessive: {$httpQueryCount}");
    }

    // =========================================================================
    // 7. Dashboard Tests
    // =========================================================================

    public function test_developer_dashboard_metrics_and_weighted_average(): void
    {
        $dev = $this->createDeveloper();
        $cat = $this->createActiveCategory();

        // App 1: Approved, 2 reviews, average 5.00, 200 installs
        $app1 = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق أ',
            'install_count' => 200,
            'review_count' => 2,
            'rating_average' => 5.00,
        ]);

        // App 2: Approved, 8 reviews, average 3.00, 800 installs
        $app2 = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق ب',
            'install_count' => 800,
            'review_count' => 8,
            'rating_average' => 3.00,
        ]);

        // App 3: Pending, 0 installs
        Pwa::factory()->pending()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'install_count' => 0,
            'review_count' => 0,
            'rating_average' => 0.00,
        ]);

        // App 4: Rejected, 50 installs
        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'status' => PwaStatus::Rejected,
            'install_count' => 50,
            'review_count' => 0,
            'rating_average' => 0.00,
        ]);

        // Create reviews on app1 and app2 by different users
        $reviewer = User::factory()->create(['email' => 'reviewer_secret@example.com']);
        $review = Review::factory()->create([
            'pwa_id' => $app1->id,
            'user_id' => $reviewer->id,
            'rating' => 5,
            'title' => 'مراجعة ممتازة',
            'is_approved' => true,
        ]);

        // Direct Service Query Count: Exactly 3 queries (stats group, top 5, recent reviews join)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $serviceData = app(DeveloperDashboardService::class)->getDashboardData($dev);

        $serviceQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, $serviceQueries, "Developer dashboard service executed {$serviceQueries} queries, expected <= 3");

        // HTTP Query Count: Service queries (3) + Auth & Spatie permissions middleware <= 7
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/developer/dashboard');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount, "Developer dashboard HTTP executed {$queryCount} queries, expected <= 7");

        // Weighted Average Check:
        // App 1: 5.00 * 2 = 10.00
        // App 2: 3.00 * 8 = 24.00
        // Total = 34.00 / 10 = 3.40!
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'counts' => [
                        'draft' => 0,
                        'pending' => 1,
                        'approved' => 2,
                        'rejected' => 1,
                    ],
                    'total_installs' => 1050, // 200 + 800 + 0 + 50
                    'total_reviews' => 10,    // 2 + 8
                    'average_rating' => 3.40,
                ],
            ])
            ->assertJsonCount(4, 'data.top_pwas')
            ->assertJsonPath('data.top_pwas.0.id', $app2->id) // 800 installs first
            ->assertJsonCount(1, 'data.recent_reviews')
            ->assertJsonPath('data.recent_reviews.0.reviewer.first_name', $reviewer->first_name)
            ->assertJsonMissing(['email' => 'reviewer_secret@example.com']);
    }

    public function test_developer_dashboard_for_new_developer_returns_zeroes(): void
    {
        $this->createDeveloper();

        $response = $this->getJson('/api/v1/developer/dashboard');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'counts' => [
                        'draft' => 0,
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0,
                    ],
                    'total_installs' => 0,
                    'total_reviews' => 0,
                    'average_rating' => 0.00,
                    'top_pwas' => [],
                    'recent_reviews' => [],
                ],
            ]);
    }
}
