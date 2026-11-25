<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EndToEndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_full_pwa_lifecycle_end_to_end(): void
    {
        // 0. Setup Category and Moderator
        $category = Category::factory()->create([
            'name' => 'Productivity Tools',
            'name_ar' => 'أدوات الإنتاجية',
            'is_active' => true,
        ]);

        $moderatorPassword = 'ModeratorPassword123!';
        $moderator = User::factory()->create([
            'email' => 'moderator@pwa-store.com',
            'username' => 'super_moderator',
            'password' => Hash::make($moderatorPassword),
            'is_active' => true,
        ]);
        $moderator->assignRole('moderator');

        // ---------------------------------------------------------------------
        // Step 1: Developer Registers
        // ---------------------------------------------------------------------
        $devRegisterResponse = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'عمر',
            'last_name' => 'المطور',
            'username' => 'omar_developer',
            'email' => 'omar@dev.test',
            'password' => 'SecureDevPassword123!',
            'password_confirmation' => 'SecureDevPassword123!',
            'role' => 'developer',
        ]);

        $devRegisterResponse->assertCreated()
            ->assertJsonPath('success', true);

        $devToken = $devRegisterResponse->json('data.token');
        $this->assertNotEmpty($devToken);

        // ---------------------------------------------------------------------
        // Step 2: Developer Submits PWA
        // ---------------------------------------------------------------------
        $pwaPayload = [
            'category_id' => $category->id,
            'name' => 'تطبيق المهام السريع',
            'name_ar' => 'تطبيق المهام السريع',
            'short_description' => 'تطبيق PWA رائع لتنظيم المهام اليومية بسرعة.',
            'short_description_ar' => 'تطبيق PWA رائع لتنظيم المهام اليومية بسرعة.',
            'description' => 'تطبيق شامل لإدارة المهام والمشاريع الصغيرة مع دعم العمل دون اتصال بالإنترنت.',
            'description_ar' => 'تطبيق شامل لإدارة المهام والمشاريع الصغيرة مع دعم العمل دون اتصال بالإنترنت.',
            'manifest_url' => 'https://taskfast.app/manifest.json',
            'start_url' => 'https://taskfast.app/',
            'icon_url' => 'https://taskfast.app/icons/icon-512x512.png',
            'screenshots' => [
                'https://taskfast.app/screenshots/screen1.png',
                'https://taskfast.app/screenshots/screen2.png',
            ],
            'version' => '1.0.0',
        ];

        $submitResponse = $this->withHeader('Authorization', "Bearer {$devToken}")
            ->postJson('/api/v1/developer/pwas', $pwaPayload);

        $submitResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_published', false);

        $pwaId = $submitResponse->json('data.id');
        $pwaSlug = $submitResponse->json('data.slug');
        $this->assertNotNull($pwaId);
        $this->assertNotEmpty($pwaSlug);

        // Verify it is NOT visible in public list yet
        $this->getJson('/api/v1/pwas')
            ->assertOk()
            ->assertJsonMissing(['slug' => $pwaSlug]);

        // Clear in-memory auth guard state before switching users
        $this->app['auth']->guard('sanctum')->forgetUser();

        // ---------------------------------------------------------------------
        // Step 3: Moderator Logs In
        // ---------------------------------------------------------------------
        $moderatorLoginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'moderator@pwa-store.com',
            'password' => $moderatorPassword,
        ]);

        $moderatorLoginResponse->assertOk()
            ->assertJsonPath('success', true);

        $moderatorToken = $moderatorLoginResponse->json('data.token');
        $this->assertNotEmpty($moderatorToken);

        // ---------------------------------------------------------------------
        // Step 4: Moderator Reviews and Approves PWA
        // ---------------------------------------------------------------------
        $approveResponse = $this->withHeader('Authorization', "Bearer {$moderatorToken}")
            ->postJson("/api/v1/moderator/pwas/{$pwaId}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.is_published', true);

        // ---------------------------------------------------------------------
        // Step 5: Public Verification - PWA is now visible
        // ---------------------------------------------------------------------
        $publicListResponse = $this->getJson('/api/v1/pwas');
        $publicListResponse->assertOk()
            ->assertJsonFragment(['slug' => $pwaSlug]);

        $publicDetailResponse = $this->getJson("/api/v1/pwas/{$pwaSlug}");
        $publicDetailResponse->assertOk()
            ->assertJsonPath('data.slug', $pwaSlug)
            ->assertJsonPath('data.install_count', 0)
            ->assertJsonPath('data.rating_average', 0)
            ->assertJsonPath('data.review_count', 0);

        // Clear in-memory auth guard state before switching to normal user
        $this->app['auth']->guard('sanctum')->forgetUser();

        // ---------------------------------------------------------------------
        // Step 6: Normal User Registers
        // ---------------------------------------------------------------------
        $userRegisterResponse = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'سارة',
            'last_name' => 'المستخدمة',
            'username' => 'sara_user',
            'email' => 'sara@user.test',
            'password' => 'SecureUserPassword123!',
            'password_confirmation' => 'SecureUserPassword123!',
            'role' => 'user',
        ]);

        $userRegisterResponse->assertCreated()
            ->assertJsonPath('success', true);

        $userToken = $userRegisterResponse->json('data.token');
        $this->assertNotEmpty($userToken);

        // ---------------------------------------------------------------------
        // Step 7: User Installs PWA
        // ---------------------------------------------------------------------
        $installResponse = $this->withHeader('Authorization', "Bearer {$userToken}")
            ->postJson("/api/v1/pwas/{$pwaSlug}/install");

        $installResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.install_count', 1);

        // ---------------------------------------------------------------------
        // Step 8: User Writes a Review
        // ---------------------------------------------------------------------
        $reviewResponse = $this->withHeader('Authorization', "Bearer {$userToken}")
            ->postJson("/api/v1/pwas/{$pwaSlug}/reviews", [
                'rating' => 5,
                'title' => 'تطبيق رائع جداً وسريع الاستجابة',
                'comment' => 'تجربة الاستخدام ممتازة وسلسة وتعمل دون اتصال بكفاءة عالية.',
            ]);

        $reviewResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5);

        // ---------------------------------------------------------------------
        // Step 9: Verify PWA Details Reflect Updated install_count and rating_average
        // ---------------------------------------------------------------------
        $finalDetailResponse = $this->getJson("/api/v1/pwas/{$pwaSlug}");
        $finalDetailResponse->assertOk()
            ->assertJsonPath('data.install_count', 1)
            ->assertJsonPath('data.rating_average', 5)
            ->assertJsonPath('data.review_count', 1);
    }
}
