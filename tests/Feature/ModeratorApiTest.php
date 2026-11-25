<?php

namespace Tests\Feature;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\ModerationLog;
use App\Models\Pwa;
use App\Models\User;
use App\Services\ModerationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModeratorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function createModerator(): User
    {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $moderator->load(['roles.permissions', 'permissions']);
        Sanctum::actingAs($moderator, ['*']);

        return $moderator;
    }

    protected function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->load(['roles.permissions', 'permissions']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    protected function createDeveloper(): User
    {
        $dev = User::factory()->create();
        $dev->assignRole('developer');

        return $dev;
    }

    protected function createPendingPwa(?User $developer = null): Pwa
    {
        $dev = $developer ?? $this->createDeveloper();
        $cat = Category::factory()->create(['is_active' => true]);

        return Pwa::factory()->pending()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق قيد المراجعة',
            'is_published' => false,
            'install_count' => 0,
            'rating_average' => 0.00,
            'review_count' => 0,
        ]);
    }

    // =========================================================================
    // 1. Authorization Tests across all roles
    // =========================================================================

    public function test_authorization_checks_across_all_roles(): void
    {
        $pwa = $this->createPendingPwa();

        $routes = [
            ['GET', '/api/v1/moderator/pwas/pending', []],
            ['GET', "/api/v1/moderator/pwas/{$pwa->id}", []],
            ['POST', "/api/v1/moderator/pwas/{$pwa->id}/approve", []],
            ['POST', "/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => 'السبب غير مطابق']],
        ];

        // 1. Unauthenticated -> 401
        foreach ($routes as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertUnauthorized();
        }

        // 2. Regular User -> 403 Forbidden
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user, ['*']);

        foreach ($routes as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertForbidden();
        }

        // 3. Developer -> 403 Forbidden
        $dev = User::factory()->create();
        $dev->assignRole('developer');
        Sanctum::actingAs($dev, ['*']);

        foreach ($routes as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertForbidden();
        }

        // 4. Moderator -> allowed (200 on GET routes)
        $this->createModerator();
        $this->getJson('/api/v1/moderator/pwas/pending')->assertOk();
        $this->getJson("/api/v1/moderator/pwas/{$pwa->id}")->assertOk();

        // 5. Admin -> allowed (200 on GET routes)
        $this->createAdmin();
        $this->getJson('/api/v1/moderator/pwas/pending')->assertOk();
        $this->getJson("/api/v1/moderator/pwas/{$pwa->id}")->assertOk();
    }

    // =========================================================================
    // 2. Pending PWAs List & FIFO Order & Sensitive Info Hiding
    // =========================================================================

    public function test_pending_pwas_list_shows_only_pending_in_fifo_order_and_hides_sensitive_developer_info(): void
    {
        $this->createModerator();
        $cat = Category::factory()->create(['is_active' => true]);

        $dev1 = User::factory()->create([
            'email' => 'dev1_secret@pwa.store',
            'phone' => '+966501111111',
        ]);
        $dev2 = User::factory()->create([
            'email' => 'dev2_secret@pwa.store',
            'phone' => '+966502222222',
        ]);

        // Oldest pending application (App 1)
        $app1 = Pwa::factory()->pending()->create([
            'developer_id' => $dev1->id,
            'category_id' => $cat->id,
            'name' => 'التطبيق الأول الأقدم',
        ]);

        // Newer pending application (App 2)
        $app2 = Pwa::factory()->pending()->create([
            'developer_id' => $dev2->id,
            'category_id' => $cat->id,
            'name' => 'التطبيق الثاني الأحدث',
        ]);

        // Approved and Rejected applications (should NOT appear)
        Pwa::factory()->approved()->create(['category_id' => $cat->id]);
        Pwa::factory()->create(['status' => PwaStatus::Rejected, 'category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/moderator/pwas/pending');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            // FIFO: App 1 appears first, then App 2
            ->assertJsonPath('data.0.id', $app1->id)
            ->assertJsonPath('data.1.id', $app2->id)
            // Developer public info is present
            ->assertJsonPath('data.0.developer.username', $dev1->username)
            ->assertJsonPath('data.0.developer.first_name', $dev1->first_name)
            // Sensitive info must NOT appear
            ->assertJsonMissing([
                'email' => 'dev1_secret@pwa.store',
                'phone' => '+966501111111',
                'email' => 'dev2_secret@pwa.store',
                'phone' => '+966502222222',
            ]);
    }

    // =========================================================================
    // 3. Approve PWA & Audit Log & Cache Invalidation
    // =========================================================================

    public function test_approve_pwa_success_lifecycle_audit_log_and_cache_invalidation(): void
    {
        $moderator = $this->createModerator();
        $pwa = $this->createPendingPwa();

        // Warm categories cache
        Cache::put(Category::CACHE_KEY, 'cached_tree', 600);
        $this->assertTrue(Cache::has(Category::CACHE_KEY));

        $response = $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/approve");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تمت الموافقة على التطبيق ونشره بنجاح.',
                'data' => [
                    'id' => $pwa->id,
                    'status' => 'approved',
                    'is_published' => true,
                    'rejection_reason' => null,
                ],
            ]);

        $fresh = $pwa->fresh();
        $this->assertSame(PwaStatus::Approved, $fresh->status);
        $this->assertTrue((bool) $fresh->is_published);
        $this->assertSame($moderator->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertNull($fresh->rejection_reason);

        // Audit Log verification
        $this->assertDatabaseHas('moderation_logs', [
            'pwa_id' => $pwa->id,
            'moderator_id' => $moderator->id,
            'action' => 'approved',
            'reason' => null,
        ]);

        // Cache was invalidated
        $this->assertFalse(Cache::has(Category::CACHE_KEY));

        // App immediately appears in public endpoints
        $this->getJson('/api/v1/pwas')
            ->assertOk()
            ->assertJsonFragment(['slug' => $pwa->slug]);

        $this->getJson("/api/v1/pwas/{$pwa->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $pwa->id);
    }

    public function test_categories_pwas_count_updates_after_approval_and_recalculation(): void
    {
        $moderator = $this->createModerator();
        $category = Category::factory()->create([
            'is_active' => true,
            'parent_id' => null,
        ]);

        // 1. Initial categories request (populates cache)
        $resBefore = $this->getJson('/api/v1/categories')->assertOk();
        $catDataBefore = collect($resBefore->json('data'))->firstWhere('id', $category->id);
        $this->assertNotNull($catDataBefore);
        $this->assertSame(0, $catDataBefore['pwas_count']);

        // 2. Create pending PWA in this category
        $pwa = $this->createPendingPwa();
        $pwa->update(['category_id' => $category->id]);

        // While pending, pwas_count should still be cached as 0
        $resCached = $this->getJson('/api/v1/categories')->assertOk();
        $catDataCached = collect($resCached->json('data'))->firstWhere('id', $category->id);
        $this->assertSame(0, $catDataCached['pwas_count']);

        // 3. Approve PWA -> invalidates Category::CACHE_KEY
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/approve")->assertOk();

        // 4. Request categories again -> recalculated from DB
        $resAfter = $this->getJson('/api/v1/categories')->assertOk();
        $catDataAfter = collect($resAfter->json('data'))->firstWhere('id', $category->id);
        $this->assertNotNull($catDataAfter);
        $this->assertSame(1, $catDataAfter['pwas_count']);
    }

    // =========================================================================
    // 4. Reject PWA & Sanitization & Developer Visibility
    // =========================================================================

    public function test_reject_pwa_requires_sanitized_reason_and_logs_audit(): void
    {
        $moderator = $this->createModerator();
        $dev = $this->createDeveloper();
        $pwa = $this->createPendingPwa($dev);

        // 1. Missing reason -> 422
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        // 2. Short reason (< 10 chars) -> 422
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => 'قصير'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        // 2b. Reason with only HTML tags (e.g. <b></b>) -> sanitized to empty -> 422
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => '<b></b>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => '<div>   <b>   </b>   </div>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        // 3. Reason with HTML -> tags stripped and trimmed
        $reasonWithHtml = '  <b>تنبيه:</b> يجب توفير أيقونة ذات أبعاد 512x512 بدقة عالية وتوضيح سياسة الخصوصية. <script>alert(1)</script> ';
        $expectedCleanReason = 'تنبيه: يجب توفير أيقونة ذات أبعاد 512x512 بدقة عالية وتوضيح سياسة الخصوصية. alert(1)';

        $response = $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", [
            'reason' => $reasonWithHtml,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم رفض التطبيق بنجاح وتسجيل السبب.',
                'data' => [
                    'id' => $pwa->id,
                    'status' => 'rejected',
                    'is_published' => false,
                    'rejection_reason' => $expectedCleanReason,
                ],
            ]);

        $fresh = $pwa->fresh();
        $this->assertSame(PwaStatus::Rejected, $fresh->status);
        $this->assertFalse((bool) $fresh->is_published);
        $this->assertSame($moderator->id, $fresh->reviewed_by);
        $this->assertSame($expectedCleanReason, $fresh->rejection_reason);

        // Audit Log verification
        $this->assertDatabaseHas('moderation_logs', [
            'pwa_id' => $pwa->id,
            'moderator_id' => $moderator->id,
            'action' => 'rejected',
            'reason' => $expectedCleanReason,
        ]);

        // Must NOT appear in public list
        $this->getJson('/api/v1/pwas')
            ->assertOk()
            ->assertJsonMissing(['slug' => $pwa->slug]);

        // Developer sees rejection_reason in Developer Portal
        Sanctum::actingAs($dev, ['*']);
        $this->getJson('/api/v1/developer/pwas')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.rejection_reason', $expectedCleanReason);
    }

    // =========================================================================
    // 5. Approve & Reject on Non-Pending PWAs Return 422
    // =========================================================================

    public function test_approve_and_reject_fail_for_non_pending_pwa(): void
    {
        $this->createModerator();
        $cat = Category::factory()->create(['is_active' => true]);

        $approvedPwa = Pwa::factory()->approved()->create(['category_id' => $cat->id]);
        $rejectedPwa = Pwa::factory()->create(['status' => PwaStatus::Rejected, 'category_id' => $cat->id]);
        $draftPwa = Pwa::factory()->draft()->create(['category_id' => $cat->id]);

        foreach ([$approvedPwa, $rejectedPwa, $draftPwa] as $pwa) {
            $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/approve")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);

            $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => 'سبب الرفض هنا مكتمل'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }
    }

    // =========================================================================
    // 6. Full Lifecycle: Submit -> Reject -> Resubmit -> Approve
    // =========================================================================

    public function test_full_lifecycle_submit_reject_resubmit_approve(): void
    {
        $dev = User::factory()->create();
        $dev->assignRole('developer');
        $moderator = $this->createModerator();
        $cat = Category::factory()->create(['is_active' => true]);

        // 1. Developer submits new PWA
        Sanctum::actingAs($dev, ['*']);
        $submitResponse = $this->postJson('/api/v1/developer/pwas', [
            'name' => 'تطبيق دورة الحياة الشاملة',
            'category_id' => $cat->id,
            'short_description' => 'وصف مختصر للتطبيق',
            'manifest_url' => 'https://lifecycle.app/manifest.json',
            'start_url' => 'https://lifecycle.app/',
        ])->assertCreated();

        $pwaId = $submitResponse->json('data.id');
        $slug = $submitResponse->json('data.slug');

        // 2. Moderator rejects the PWA
        Sanctum::actingAs($moderator, ['*']);
        $this->postJson("/api/v1/moderator/pwas/{$pwaId}/reject", [
            'reason' => 'يرجى تزويدنا بأيقونة ولقطات شاشة مناسبة للتطبيق.',
        ])->assertOk();

        $this->assertSame(1, ModerationLog::where('pwa_id', $pwaId)->count());

        // 3. Developer updates and resubmits
        Sanctum::actingAs($dev, ['*']);
        $this->putJson("/api/v1/developer/pwas/{$pwaId}", [
            'icon_url' => 'https://lifecycle.app/icon-512.png',
        ])->assertOk();

        $this->postJson("/api/v1/developer/pwas/{$pwaId}/resubmit")->assertOk();

        $this->assertSame(PwaStatus::Pending, Pwa::find($pwaId)->status);

        // 4. Moderator approves the PWA
        Sanctum::actingAs($moderator, ['*']);
        $this->postJson("/api/v1/moderator/pwas/{$pwaId}/approve")->assertOk();

        // Verification: 2 audit logs exist
        $logs = ModerationLog::where('pwa_id', $pwaId)->orderBy('id', 'asc')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('rejected', $logs[0]->action);
        $this->assertSame('approved', $logs[1]->action);

        // Visible in public API
        $this->getJson("/api/v1/pwas/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $pwaId);
    }

    // =========================================================================
    // 7. Moderator Cannot Review Own PWA
    // =========================================================================

    public function test_moderator_cannot_review_own_pwa(): void
    {
        $dualUser = User::factory()->create();
        $dualUser->assignRole(['developer', 'moderator']);
        Sanctum::actingAs($dualUser, ['*']);

        $pwa = $this->createPendingPwa($dualUser);

        // Attempting to approve own PWA -> 403 Forbidden
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/approve")
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'لا يمكنك مراجعة أو تغيير حالة تطبيق تملكه.',
            ]);

        // Attempting to reject own PWA -> 403 Forbidden
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => 'سبب الرفض هنا مكتمل'])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'لا يمكنك مراجعة أو تغيير حالة تطبيق تملكه.',
            ]);
    }

    // =========================================================================
    // 8. Concurrency: Simulated Second Decision Returns 422
    // =========================================================================

    public function test_concurrency_simulated_second_decision_fails_with_422(): void
    {
        $mod1 = User::factory()->create();
        $mod1->assignRole('moderator');
        $mod2 = User::factory()->create();
        $mod2->assignRole('moderator');

        $pwa = $this->createPendingPwa();

        // Moderator 1 approves
        Sanctum::actingAs($mod1, ['*']);
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/approve")->assertOk();

        // Moderator 2 simultaneously tries to approve or reject the already approved PWA
        Sanctum::actingAs($mod2, ['*']);
        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->postJson("/api/v1/moderator/pwas/{$pwa->id}/reject", ['reason' => 'محاولة رفض متأخرة'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // =========================================================================
    // 9. Query Count Assertion for Pending PWAs List
    // =========================================================================

    public function test_pending_pwas_list_query_count_is_minimal(): void
    {
        $this->createModerator();
        $cat = Category::factory()->create(['is_active' => true]);

        for ($i = 0; $i < 10; $i++) {
            Pwa::factory()->pending()->create([
                'category_id' => $cat->id,
            ]);
        }

        // Test Service directly: 1 count, 1 select pwas, 1 eager load categories, 1 eager load developers = 4
        DB::flushQueryLog();
        DB::enableQueryLog();

        $serviceResult = app(ModerationService::class)->getPendingPwas([], 10);

        $serviceQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $serviceQueryCount, "Service logic executed {$serviceQueryCount} queries, expected <= 4");

        // Test HTTP endpoint: 4 queries + Spatie/Sanctum <= 8
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/moderator/pwas/pending?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $httpQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $httpQueryCount, "HTTP query count excessive: {$httpQueryCount}");
    }
}
