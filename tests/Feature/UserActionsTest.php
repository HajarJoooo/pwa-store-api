<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Installation;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function createAuthenticatedUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    protected function createPublishedPwa(?User $developer = null): Pwa
    {
        $dev = $developer ?? User::factory()->create();
        $cat = Category::factory()->create();

        return Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'install_count' => 10,
            'rating_average' => 0.00,
            'review_count' => 0,
        ]);
    }

    // =========================================================================
    // 1. Installations Tests
    // =========================================================================

    public function test_first_installation_returns_201_and_increments_counter(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        $response = $this->postJson("/api/v1/pwas/{$pwa->slug}/install");

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'تم تثبيت التطبيق بنجاح.',
                'data' => [
                    'install_count' => 11,
                ],
            ])
            ->assertJsonStructure([
                'data' => ['installed_at', 'install_count'],
            ]);

        $this->assertDatabaseHas('installations', [
            'user_id' => $user->id,
            'pwa_id' => $pwa->id,
        ]);

        $this->assertSame(11, (int) $pwa->fresh()->install_count);
    }

    public function test_repeated_installation_is_idempotent_returns_200_without_increment(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        // First install
        $this->postJson("/api/v1/pwas/{$pwa->slug}/install")->assertCreated();
        $this->assertSame(11, (int) $pwa->fresh()->install_count);

        // Second install by same user
        $response = $this->postJson("/api/v1/pwas/{$pwa->slug}/install");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'التطبيق مثبت لديك مسبقاً.',
                'data' => [
                    'install_count' => 11,
                ],
            ]);

        // Counter must remain 11 and only 1 record in installations table
        $this->assertSame(11, (int) $pwa->fresh()->install_count);
        $this->assertSame(1, Installation::where('user_id', $user->id)->where('pwa_id', $pwa->id)->count());
    }

    public function test_installation_returns_404_for_unpublished_pwa(): void
    {
        $this->createAuthenticatedUser();
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $draft = Pwa::factory()->draft()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'draft-pwa-app',
        ]);

        $this->postJson("/api/v1/pwas/{$draft->slug}/install")
            ->assertNotFound();
    }

    public function test_installation_requires_authentication(): void
    {
        $pwa = $this->createPublishedPwa();

        $this->postJson("/api/v1/pwas/{$pwa->slug}/install")
            ->assertUnauthorized();
    }

    public function test_installation_returns_403_for_user_without_permission(): void
    {
        $userWithoutRole = User::factory()->create();
        Sanctum::actingAs($userWithoutRole, ['*']);

        $pwa = $this->createPublishedPwa();

        $this->postJson("/api/v1/pwas/{$pwa->slug}/install")
            ->assertForbidden();
    }

    public function test_get_user_installations_returns_only_authenticated_user_apps(): void
    {
        $user1 = $this->createAuthenticatedUser();
        $pwa1 = $this->createPublishedPwa();
        $pwa2 = $this->createPublishedPwa();

        Installation::create([
            'user_id' => $user1->id,
            'pwa_id' => $pwa1->id,
            'installed_at' => now()->subDay(),
        ]);
        Installation::create([
            'user_id' => $user1->id,
            'pwa_id' => $pwa2->id,
            'installed_at' => now(),
        ]);

        // Another user's installation
        $user2 = User::factory()->create();
        $pwa3 = $this->createPublishedPwa();
        Installation::create([
            'user_id' => $user2->id,
            'pwa_id' => $pwa3->id,
            'installed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/me/installations');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pwa2->id) // latest installed first
            ->assertJsonPath('data.1.id', $pwa1->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'short_description',
                        'icon_url',
                        'version',
                        'rating_average',
                        'review_count',
                        'install_count',
                        'category',
                        'installed_at',
                    ],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_get_user_installations_hides_unpublished_pwas(): void
    {
        $user = $this->createAuthenticatedUser();
        $published = $this->createPublishedPwa();

        $dev = User::factory()->create();
        $cat = Category::factory()->create();
        $draft = Pwa::factory()->draft()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
        ]);

        Installation::create(['user_id' => $user->id, 'pwa_id' => $published->id]);
        Installation::create(['user_id' => $user->id, 'pwa_id' => $draft->id]);

        $response = $this->getJson('/api/v1/me/installations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_get_user_installations_query_count_is_minimal_no_n_plus_one(): void
    {
        $user = $this->createAuthenticatedUser();
        $cat = Category::factory()->create();
        $dev = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $pwa = Pwa::factory()->approved()->create([
                'developer_id' => $dev->id,
                'category_id' => $cat->id,
            ]);
            Installation::create([
                'user_id' => $user->id,
                'pwa_id' => $pwa->id,
                'installed_at' => now(),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/me/installations?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 count, 1 select installations, 1 select pwas, 1 select categories
        $this->assertLessThanOrEqual(4, $queryCount, "Detected N+1 queries in installations list. Total: {$queryCount}");
    }

    // =========================================================================
    // 2. Reviews Tests
    // =========================================================================

    public function test_user_can_create_review_for_installed_pwa(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        Installation::create([
            'user_id' => $user->id,
            'pwa_id' => $pwa->id,
        ]);

        $payload = [
            'rating' => 5,
            'title' => 'تطبيق ممتاز جداً',
            'content' => 'تجربة استخدام رائعة وسرعة استجابة فائقة.',
        ];

        $response = $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", $payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'تم إضافة تقييمك بنجاح.',
                'data' => [
                    'rating' => 5,
                    'title' => 'تطبيق ممتاز جداً',
                    'content' => 'تجربة استخدام رائعة وسرعة استجابة فائقة.',
                    'reviewer' => [
                        'first_name' => $user->first_name,
                        'username' => $user->username,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);

        $this->assertSame(5.00, (float) $pwa->fresh()->rating_average);
        $this->assertSame(1, (int) $pwa->fresh()->review_count);
    }

    public function test_developer_cannot_review_own_pwa(): void
    {
        $dev = User::factory()->create();
        $dev->assignRole('developer');
        Sanctum::actingAs($dev, ['*']);

        $pwa = $this->createPublishedPwa($dev);

        Installation::create([
            'user_id' => $dev->id,
            'pwa_id' => $pwa->id,
        ]);

        $response = $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", [
            'rating' => 5,
            'title' => 'أفضل تطبيق',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'errors' => [
                    'pwa' => ['لا يمكنك تقييم تطبيقك الخاص.'],
                ],
            ]);
    }

    public function test_user_cannot_submit_duplicate_review_for_same_pwa(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        Installation::create([
            'user_id' => $user->id,
            'pwa_id' => $pwa->id,
        ]);

        Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $response = $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", [
            'rating' => 5,
            'title' => 'محاولة تقييم ثانية',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'errors' => [
                    'review' => ['لقد قمت بتقييم هذا التطبيق مسبقاً.'],
                ],
            ]);
    }

    public function test_installation_prerequisite_controlled_by_config(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        // 1. When config is TRUE (default): Cannot review without installing
        config(['reviews.require_install' => true]);

        $responseWithoutInstall = $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", [
            'rating' => 4,
            'title' => 'بدون تثبيت',
        ]);

        $responseWithoutInstall->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'errors' => [
                    'pwa' => ['يجب تثبيت التطبيق أولاً لتتمكن من تقييمه.'],
                ],
            ]);

        // 2. When config is FALSE: Can review even without installing
        config(['reviews.require_install' => false]);

        $responseWithConfigDisabled = $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", [
            'rating' => 4,
            'title' => 'مسموح بدون تثبيت',
        ]);

        $responseWithConfigDisabled->assertCreated();
    }

    public function test_review_validation_rules_enforce_valid_rating(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();
        Installation::create(['user_id' => $user->id, 'pwa_id' => $pwa->id]);

        // Rating = 0 (out of bounds)
        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", ['rating' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);

        // Rating = 6 (out of bounds)
        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);

        // Rating missing
        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", ['title' => 'بدون تقييم'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_user_can_update_own_review_with_partial_fields(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        $review = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'rating' => 3,
            'title' => 'عنوان أولي',
            'content' => 'محتوى أولي',
        ]);

        $response = $this->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 5,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم تعديل تقييمك بنجاح.',
                'data' => [
                    'id' => $review->id,
                    'rating' => 5,
                    'title' => 'عنوان أولي', // unchanged
                ],
            ]);

        $this->assertSame(5, (int) $review->fresh()->rating);
        $this->assertSame(5.00, (float) $pwa->fresh()->rating_average);
    }

    public function test_user_cannot_update_another_users_review(): void
    {
        $owner = User::factory()->create();
        $attacker = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        $review = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $owner->id,
            'rating' => 4,
        ]);

        $this->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 1,
        ])->assertForbidden();

        $this->assertSame(4, (int) $review->fresh()->rating);
    }

    public function test_user_can_delete_own_review_soft_delete(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        $review = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);

        $response = $this->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم حذف التقييم بنجاح.',
            ]);

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
        $this->assertSame(0.00, (float) $pwa->fresh()->rating_average);
        $this->assertSame(0, (int) $pwa->fresh()->review_count);
    }

    public function test_user_cannot_delete_another_users_review(): void
    {
        $owner = User::factory()->create();
        $attacker = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();

        $review = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $owner->id,
        ]);

        $this->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('reviews', ['id' => $review->id]);
    }

    public function test_rating_average_recalculates_accurately_with_two_decimals(): void
    {
        $pwa = $this->createPublishedPwa();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        foreach ([$user1, $user2, $user3] as $u) {
            $u->assignRole('user');
            Installation::create(['user_id' => $u->id, 'pwa_id' => $pwa->id]);
        }

        // User 1 reviews with 5
        Sanctum::actingAs($user1, ['*']);
        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", ['rating' => 5])->assertCreated();
        $this->assertSame(5.00, (float) $pwa->fresh()->rating_average);
        $this->assertSame(1, (int) $pwa->fresh()->review_count);

        // User 2 reviews with 4
        Sanctum::actingAs($user2, ['*']);
        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", ['rating' => 4])->assertCreated();
        $this->assertSame(4.50, (float) $pwa->fresh()->rating_average);
        $this->assertSame(2, (int) $pwa->fresh()->review_count);

        // User 3 reviews with 4 (5 + 4 + 4 = 13 / 3 = 4.33333 -> 4.33)
        Sanctum::actingAs($user3, ['*']);
        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", ['rating' => 4])->assertCreated();
        $this->assertSame(4.33, (float) $pwa->fresh()->rating_average);
        $this->assertSame(3, (int) $pwa->fresh()->review_count);

        // User 3 updates review to 5 (5 + 4 + 5 = 14 / 3 = 4.66667 -> 4.67)
        $review3 = Review::where('user_id', $user3->id)->first();
        $this->putJson("/api/v1/reviews/{$review3->id}", ['rating' => 5])->assertOk();
        $this->assertSame(4.67, (float) $pwa->fresh()->rating_average);
        $this->assertSame(3, (int) $pwa->fresh()->review_count);

        // User 2 deletes review (5 + 5 = 10 / 2 = 5.00)
        Sanctum::actingAs($user2, ['*']);
        $review2 = Review::where('user_id', $user2->id)->first();
        $this->deleteJson("/api/v1/reviews/{$review2->id}")->assertOk();
        $this->assertSame(5.00, (float) $pwa->fresh()->rating_average);
        $this->assertSame(2, (int) $pwa->fresh()->review_count);

        // Delete remaining reviews: count and average should drop to 0
        Sanctum::actingAs($user1, ['*']);
        $review1 = Review::where('user_id', $user1->id)->first();
        $this->deleteJson("/api/v1/reviews/{$review1->id}")->assertOk();

        Sanctum::actingAs($user3, ['*']);
        $this->deleteJson("/api/v1/reviews/{$review3->id}")->assertOk();

        $this->assertSame(0.00, (float) $pwa->fresh()->rating_average);
        $this->assertSame(0, (int) $pwa->fresh()->review_count);
    }

    public function test_submitting_review_restores_soft_deleted_review_without_conflict(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();
        Installation::create(['user_id' => $user->id, 'pwa_id' => $pwa->id]);

        // Create initial review
        $review = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'rating' => 2,
            'title' => 'تقييم قديم',
        ]);

        // Soft delete the review
        $this->deleteJson("/api/v1/reviews/{$review->id}")->assertOk();
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);

        // Submit new review for the same app
        $response = $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", [
            'rating' => 5,
            'title' => 'تقييم جديد بعد الاستعادة',
            'content' => 'تم حل المشاكل وأصبح التطبيق ممتازاً.',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $review->id, // Reuses same ID
                    'rating' => 5,
                    'title' => 'تقييم جديد بعد الاستعادة',
                ],
            ]);

        // Verify it was restored in DB and total reviews is still 1
        $this->assertNotSoftDeleted('reviews', ['id' => $review->id]);
        $this->assertSame(1, Review::where('pwa_id', $pwa->id)->where('user_id', $user->id)->count());
        $this->assertSame(5.00, (float) $pwa->fresh()->rating_average);
        $this->assertSame(1, (int) $pwa->fresh()->review_count);
    }

    public function test_newly_created_review_appears_in_public_pwa_reviews_endpoint(): void
    {
        $user = $this->createAuthenticatedUser();
        $pwa = $this->createPublishedPwa();
        Installation::create(['user_id' => $user->id, 'pwa_id' => $pwa->id]);

        $this->postJson("/api/v1/pwas/{$pwa->slug}/reviews", [
            'rating' => 5,
            'title' => 'مراجعة مرئية للجميع',
            'content' => 'محتوى تجريبي للتحقق من الظهور العام.',
        ])->assertCreated();

        // Unauthenticated call to public endpoint
        $publicResponse = $this->getJson("/api/v1/pwas/{$pwa->slug}/reviews");

        $publicResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'مراجعة مرئية للجميع')
            ->assertJsonPath('data.0.rating', 5);
    }
}
