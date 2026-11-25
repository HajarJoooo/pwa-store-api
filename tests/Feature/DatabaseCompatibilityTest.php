<?php

namespace Tests\Feature;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\Installation;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use App\Services\RatingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Unique index with Soft Delete.
     * Verifies that a unique constraint on slug prevents duplicate slugs even when the old record is soft-deleted.
     */
    public function test_unique_slug_constraint_with_soft_delete(): void
    {
        $dev = User::factory()->create();
        $category = Category::factory()->create();

        $pwa1 = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $category->id,
            'slug' => 'unique-slug-test',
        ]);

        // Soft delete the first PWA
        $pwa1->delete();
        $this->assertSoftDeleted('pwas', ['id' => $pwa1->id]);

        // Attempting to create another PWA with the identical slug must violate unique constraint
        $this->expectException(QueryException::class);

        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $category->id,
            'slug' => 'unique-slug-test',
        ]);
    }

    /**
     * 2. Enum behavior on invalid values.
     * Verifies that invalid enum values cannot be assigned to backed enum attributes.
     */
    public function test_enum_behavior_rejects_invalid_values(): void
    {
        $dev = User::factory()->create();
        $category = Category::factory()->create();

        $pwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $category->id,
            'status' => PwaStatus::Pending,
        ]);

        $this->expectException(\ValueError::class);

        // Attempt to assign an invalid status not present in PwaStatus enum
        $pwa->status = PwaStatus::from('non_existent_status');
    }

    /**
     * 3. AVG function rounding.
     * Verifies that rating calculation properly rounds floating point averages to exactly 2 decimal places.
     */
    public function test_avg_rating_calculation_and_rounding(): void
    {
        $dev = User::factory()->create();
        $category = Category::factory()->create();

        $pwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $category->id,
            'status' => PwaStatus::Approved,
            'is_published' => true,
            'rating_average' => 0.00,
            'review_count' => 0,
        ]);

        // Add 3 reviews with ratings 5, 4, 4: (5 + 4 + 4) / 3 = 13 / 3 = 4.3333333333...
        $users = User::factory()->count(3)->create();
        $ratings = [5, 4, 4];

        foreach ($users as $index => $user) {
            Review::create([
                'pwa_id' => $pwa->id,
                'user_id' => $user->id,
                'rating' => $ratings[$index],
                'title' => 'مراجعة '.($index + 1),
                'comment' => 'هذا تعليق تجريبي لاختبار التقريب الرياضي لدالة المتوسط.',
            ]);
        }

        $ratingService = app(RatingService::class);
        $ratingService->recalculate($pwa);

        $fresh = $pwa->fresh();
        $this->assertEquals(3, $fresh->review_count);
        $this->assertEquals(4.33, (float) $fresh->rating_average);
    }

    /**
     * 4. Foreign Keys integrity: Restrict on Delete.
     * Verifies that deleting a category with associated PWAs is restricted.
     */
    public function test_foreign_key_restrict_prevents_category_deletion_when_pwas_exist(): void
    {
        $dev = User::factory()->create();
        $category = Category::factory()->create();

        Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $category->id,
        ]);

        $this->expectException(QueryException::class);
        $category->delete();
    }

    /**
     * 5. Foreign Keys integrity: Cascade on Delete.
     * Verifies that force deleting a PWA cascades to delete associated reviews and installations.
     */
    public function test_foreign_key_cascade_deletes_associated_reviews_and_installations(): void
    {
        $dev = User::factory()->create();
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $pwa = Pwa::factory()->create([
            'developer_id' => $dev->id,
            'category_id' => $category->id,
            'status' => PwaStatus::Approved,
            'is_published' => true,
        ]);

        $installation = Installation::create([
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'installed_at' => now(),
        ]);

        $review = Review::create([
            'pwa_id' => $pwa->id,
            'user_id' => $user->id,
            'rating' => 5,
            'title' => 'تطبيق رائع',
            'comment' => 'محتوى المراجعة الممتازة للاختبار.',
        ]);

        $this->assertDatabaseHas('installations', ['id' => $installation->id]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);

        // Force delete the PWA to trigger DB-level CASCADE
        $pwa->forceDelete();

        $this->assertDatabaseMissing('pwas', ['id' => $pwa->id]);
        $this->assertDatabaseMissing('installations', ['id' => $installation->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
