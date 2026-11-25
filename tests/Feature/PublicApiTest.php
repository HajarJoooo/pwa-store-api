<?php

namespace Tests\Feature;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_pwas_list_shows_only_approved_and_published_apps(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        // 1 published app
        $published = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق منشور',
            'status' => PwaStatus::Approved,
            'is_published' => true,
        ]);

        // Draft app
        Pwa::factory()->draft()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق مسودة',
        ]);

        // Pending app
        Pwa::factory()->pending()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق قيد المراجعة',
        ]);

        // Rejected app
        Pwa::factory()->rejected()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق مرفوض',
        ]);

        // Approved but not published
        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق غير مفعل النشر',
            'status' => PwaStatus::Approved,
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/v1/pwas');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.name', 'تطبيق منشور');
    }

    public function test_pwas_search_matches_name_and_short_description(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $app1 = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق البوصلة الإسلامية',
            'short_description' => 'تحديد القبلة بدقة',
        ]);

        $app2 = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'المهام اليومية',
            'short_description' => 'تطبيق بوصلة لتنظيم الأولويات',
        ]);

        $app3 = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'ألعاب الذكاء',
            'short_description' => 'ألغاز وكلمات متقاطعة',
        ]);

        $response = $this->getJson('/api/v1/pwas?search=بوصلة');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($app1->id, $returnedIds);
        $this->assertContains($app2->id, $returnedIds);
        $this->assertNotContains($app3->id, $returnedIds);
    }

    public function test_pwas_search_escapes_wildcard_characters(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $appWithPercent = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق تخفيض 50% المميز',
            'short_description' => 'خصومات وعروض',
        ]);

        $appWithoutPercent = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق تخفيض 500 للمنتجات',
            'short_description' => 'منتجات ومتاجر',
        ]);

        // Search for "50%" - should only match the first app, not the one with 500
        $response = $this->getJson('/api/v1/pwas?search=50%');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $appWithPercent->id);
    }

    public function test_pwas_category_filter_includes_parent_and_child_categories(): void
    {
        $dev = User::factory()->create();

        $parentCat = Category::factory()->create(['slug' => 'tech-parent']);
        $childCat = Category::factory()->create([
            'slug' => 'mobile-child',
            'parent_id' => $parentCat->id,
        ]);
        $otherCat = Category::factory()->create(['slug' => 'other-category']);

        $appInParent = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $parentCat->id,
            'name' => 'تطبيق تقني عام',
        ]);

        $appInChild = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $childCat->id,
            'name' => 'تطبيق جوال فرعي',
        ]);

        $appInOther = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $otherCat->id,
            'name' => 'تطبيق تصنيف آخر',
        ]);

        $response = $this->getJson('/api/v1/pwas?category=tech-parent');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($appInParent->id, $returnedIds);
        $this->assertContains($appInChild->id, $returnedIds);
        $this->assertNotContains($appInOther->id, $returnedIds);
    }

    public function test_pwas_sorting_options(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $oldPopular = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'قديم الأكثر شعبية',
            'install_count' => 1000,
            'rating_average' => 3.5,
            'review_count' => 10,
            'created_at' => now()->subDays(10),
        ]);

        $newest = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'الأحدث إضافة',
            'install_count' => 50,
            'rating_average' => 4.0,
            'review_count' => 5,
            'created_at' => now(),
        ]);

        $topRated = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'الأعلى تقييماً',
            'install_count' => 200,
            'rating_average' => 4.95,
            'review_count' => 40,
            'created_at' => now()->subDays(5),
        ]);

        // Default / newest
        $resNewest = $this->getJson('/api/v1/pwas?sort=newest');
        $resNewest->assertOk()
            ->assertJsonPath('data.0.id', $newest->id);

        // Popular
        $resPopular = $this->getJson('/api/v1/pwas?sort=popular');
        $resPopular->assertOk()
            ->assertJsonPath('data.0.id', $oldPopular->id);

        // Top rated
        $resTopRated = $this->getJson('/api/v1/pwas?sort=top_rated');
        $resTopRated->assertOk()
            ->assertJsonPath('data.0.id', $topRated->id);
    }

    public function test_pwas_pagination_and_max_per_page(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        Pwa::factory()->count(15)->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
        ]);

        // Default per_page is 12
        $response = $this->getJson('/api/v1/pwas');
        $response->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure([
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        // Exceeding max per_page (50) returns 422
        $invalidPerPage = $this->getJson('/api/v1/pwas?per_page=51');
        $invalidPerPage->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_pwa_show_returns_details_and_hides_sensitive_fields(): void
    {
        $dev = User::factory()->create([
            'first_name' => 'فهد',
            'last_name' => 'المطور',
            'username' => 'fahad_dev',
            'email' => 'fahad_secret@example.com',
            'phone' => '+966500000000',
        ]);
        $cat = Category::factory()->create();

        $pwa = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'name' => 'تطبيق تفصيلي',
            'slug' => 'detailed-app',
            'description' => 'وصف تفصيلي شامل للتطبيق مع مميزاته التقنية',
            'manifest_url' => 'https://detailed.app/manifest.json',
            'start_url' => 'https://detailed.app/',
            'rejection_reason' => 'سر داخلي لا يجب ظهوره',
        ]);

        $response = $this->getJson('/api/v1/pwas/detailed-app');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $pwa->id,
                    'name' => 'تطبيق تفصيلي',
                    'slug' => 'detailed-app',
                    'description' => 'وصف تفصيلي شامل للتطبيق مع مميزاته التقنية',
                    'developer' => [
                        'first_name' => 'فهد',
                        'last_name' => 'المطور',
                        'username' => 'fahad_dev',
                    ],
                ],
            ])
            ->assertJsonMissing([
                'email' => 'fahad_secret@example.com',
                'phone' => '+966500000000',
                'status' => 'approved',
                'rejection_reason' => 'سر داخلي لا يجب ظهوره',
                'reviewed_by',
                'reviewed_at',
                'is_active',
            ]);
    }

    public function test_pwa_show_returns_404_for_unpublished_or_nonexistent_apps(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        // Non-existent slug
        $this->getJson('/api/v1/pwas/non-existent-app')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'المورد المطلوب غير موجود.',
            ]);

        // Draft PWA
        Pwa::factory()->draft()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'draft-app-slug',
        ]);
        $this->getJson('/api/v1/pwas/draft-app-slug')
            ->assertNotFound();

        // Pending PWA
        Pwa::factory()->pending()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'pending-app-slug',
        ]);
        $this->getJson('/api/v1/pwas/pending-app-slug')
            ->assertNotFound();

        // Rejected PWA
        Pwa::factory()->rejected()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'rejected-app-slug',
        ]);
        $this->getJson('/api/v1/pwas/rejected-app-slug')
            ->assertNotFound();
    }

    public function test_categories_list_is_cached_and_returns_published_pwas_count(): void
    {
        $cat = Category::factory()->create(['name' => 'تعليم', 'sort_order' => 1]);
        $child = Category::factory()->create(['name' => 'لغات', 'parent_id' => $cat->id, 'sort_order' => 2]);
        $dev = User::factory()->create();

        Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
        ]);
        // Unpublished PWA shouldn't be counted
        Pwa::factory()->draft()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
        ]);

        Cache::flush();

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.id', $cat->id)
            ->assertJsonPath('data.0.pwas_count', 1);

        $this->assertTrue(Cache::has(Category::CACHE_KEY));

        // Modifying a category should bust cache
        $cat->update(['name' => 'تعليم وتدريب']);
        $this->assertFalse(Cache::has(Category::CACHE_KEY));
    }

    public function test_pwa_reviews_list_shows_only_approved_and_non_deleted_reviews(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $pwa = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'review-test-app',
        ]);

        $reviewer1 = User::factory()->create([
            'first_name' => 'سامي',
            'username' => 'sami_user',
            'email' => 'sami_secret@example.com',
            'phone' => '+966555999888',
        ]);
        $reviewer2 = User::factory()->create();
        $reviewer3 = User::factory()->create();

        // 1. Approved Review
        $approvedReview = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $reviewer1->id,
            'title' => 'مراجعة معتمدة وممتازة',
            'is_approved' => true,
        ]);

        // 2. Unapproved Review
        Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $reviewer2->id,
            'title' => 'مراجعة قيد المراجعة',
            'is_approved' => false,
        ]);

        // 3. Soft-deleted Review
        $deletedReview = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $reviewer3->id,
            'title' => 'مراجعة محذوفة',
            'is_approved' => true,
        ]);
        $deletedReview->delete();

        $response = $this->getJson('/api/v1/pwas/review-test-app/reviews');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approvedReview->id)
            ->assertJsonPath('data.0.reviewer.first_name', 'سامي')
            ->assertJsonMissing([
                'email' => 'sami_secret@example.com',
                'phone' => '+966555999888',
            ]);
    }

    public function test_pwa_reviews_returns_404_if_pwa_not_published(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $draft = Pwa::factory()->draft()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'draft-reviews-app',
        ]);

        $this->getJson('/api/v1/pwas/draft-reviews-app/reviews')
            ->assertNotFound();
    }

    public function test_pwa_reviews_supports_sorting_by_newest_and_helpful(): void
    {
        $dev = User::factory()->create();
        $cat = Category::factory()->create();

        $pwa = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'slug' => 'reviews-sort-app',
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $olderHelpful = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $user1->id,
            'title' => 'قديمة ومفيدة جداً',
            'helpful_count' => 100,
            'is_approved' => true,
            'created_at' => now()->subDays(10),
        ]);

        $newerLessHelpful = Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $user2->id,
            'title' => 'جديدة بدون تفاعل',
            'helpful_count' => 0,
            'is_approved' => true,
            'created_at' => now(),
        ]);

        // Default newest
        $resNewest = $this->getJson('/api/v1/pwas/reviews-sort-app/reviews?sort=newest');
        $resNewest->assertOk()
            ->assertJsonPath('data.0.id', $newerLessHelpful->id);

        // Helpful
        $resHelpful = $this->getJson('/api/v1/pwas/reviews-sort-app/reviews?sort=helpful');
        $resHelpful->assertOk()
            ->assertJsonPath('data.0.id', $olderHelpful->id);
    }

    public function test_validation_errors_return_422_with_arabic_messages(): void
    {
        // Invalid sort
        $this->getJson('/api/v1/pwas?sort=invalid_sort')
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'بيانات الإدخال غير صالحة، يرجى تصحيح الأخطاء.',
            ])
            ->assertJsonValidationErrors(['sort']);

        // Non-existent category
        $this->getJson('/api/v1/pwas?category=does-not-exist')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        // Search too short (less than 2 chars)
        $this->getJson('/api/v1/pwas?search=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    public function test_query_count_for_pwas_list_is_minimal_no_n_plus_one(): void
    {
        $dev = User::factory()->create();
        $cats = Category::factory()->count(5)->create();

        // Create 15 PWAs across different categories
        for ($i = 0; $i < 15; $i++) {
            Pwa::factory()->approved()->create([
                'developer_id' => $dev->id,
                'category_id' => $cats->random()->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/pwas?per_page=12');
        $response->assertOk()
            ->assertJsonCount(12, 'data');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count should be strictly <= 4:
        // 1. count(*) for pagination
        // 2. select pwas with limit 12
        // 3. select categories where id in (...)
        // (plus optional cache/session query if any)
        $this->assertLessThanOrEqual(4, $queryCount, "Detected N+1 or redundant queries. Total executed: {$queryCount}");
    }

    public function test_public_responses_do_not_leak_sensitive_developer_or_reviewer_fields(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_secret@pwa.store',
            'phone' => '+966509999999',
        ]);

        $dev = User::factory()->create([
            'email' => 'dev_confidential@pwa.store',
            'phone' => '+966508888888',
        ]);

        $reviewer = User::factory()->create([
            'email' => 'reviewer_private@pwa.store',
            'phone' => '+966507777777',
        ]);

        $cat = Category::factory()->create();

        $pwa = Pwa::factory()->approved()->create([
            'developer_id' => $dev->id,
            'category_id' => $cat->id,
            'reviewed_by' => $admin->id,
            'rejection_reason' => 'hidden internal memo',
            'slug' => 'privacy-checked-app',
        ]);

        Review::factory()->create([
            'pwa_id' => $pwa->id,
            'user_id' => $reviewer->id,
            'is_approved' => true,
        ]);

        // 1. Test PWAs list
        $listResponse = $this->getJson('/api/v1/pwas');
        $listResponse->assertOk()
            ->assertJsonMissing([
                'email' => 'dev_confidential@pwa.store',
                'phone' => '+966508888888',
                'rejection_reason' => 'hidden internal memo',
                'reviewed_by' => $admin->id,
            ]);

        // 2. Test PWA details
        $showResponse = $this->getJson('/api/v1/pwas/privacy-checked-app');
        $showResponse->assertOk()
            ->assertJsonMissing([
                'email' => 'dev_confidential@pwa.store',
                'phone' => '+966508888888',
                'email' => 'admin_secret@pwa.store',
                'rejection_reason' => 'hidden internal memo',
                'reviewed_by' => $admin->id,
            ]);

        // 3. Test PWA reviews list
        $reviewsResponse = $this->getJson('/api/v1/pwas/privacy-checked-app/reviews');
        $reviewsResponse->assertOk()
            ->assertJsonMissing([
                'email' => 'reviewer_private@pwa.store',
                'phone' => '+966507777777',
            ]);
    }
}
