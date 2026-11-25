<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_create_admin_command_succeeds_with_strong_password(): void
    {
        $this->artisan('app:create-admin', [
            '--role' => 'admin',
            '--email' => 'admin.prod@pwa-store.com',
            '--username' => 'prod_admin',
        ])
            ->expectsQuestion('Enter first name (optional)', 'Master')
            ->expectsQuestion('Enter last name (optional)', 'Administrator')
            ->expectsQuestion('Enter secure password (min 12 chars with upper, lower, digit, symbol)', 'SuperSecret!2026#')
            ->expectsQuestion('Confirm secure password', 'SuperSecret!2026#')
            ->expectsOutputToContain("Successfully created admin account for 'prod_admin'")
            ->assertSuccessful();

        $user = User::where('email', 'admin.prod@pwa-store.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SuperSecret!2026#', $user->password));
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue((bool) $user->is_verified);
    }

    public function test_create_admin_command_rejects_weak_password(): void
    {
        $this->artisan('app:create-admin', [
            '--role' => 'moderator',
            '--email' => 'mod.prod@pwa-store.com',
            '--username' => 'prod_mod',
        ])
            ->expectsQuestion('Enter first name (optional)', 'Moderator')
            ->expectsQuestion('Enter last name (optional)', 'Staff')
            ->expectsQuestion('Enter secure password (min 12 chars with upper, lower, digit, symbol)', 'weakpassword')
            ->expectsOutputToContain('Password does not meet security requirements')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'mod.prod@pwa-store.com']);
    }

    public function test_create_admin_command_rejects_password_mismatch(): void
    {
        $this->artisan('app:create-admin', [
            '--role' => 'admin',
            '--email' => 'admin.mismatch@pwa-store.com',
            '--username' => 'mismatch_admin',
        ])
            ->expectsQuestion('Enter first name (optional)', 'Admin')
            ->expectsQuestion('Enter last name (optional)', 'Mismatch')
            ->expectsQuestion('Enter secure password (min 12 chars with upper, lower, digit, symbol)', 'ValidSecret#2026!')
            ->expectsQuestion('Confirm secure password', 'DifferentSecret#2026!')
            ->expectsOutputToContain('Password confirmation does not match')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin.mismatch@pwa-store.com']);
    }

    public function test_create_admin_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@pwa-store.com']);

        $this->artisan('app:create-admin', [
            '--role' => 'admin',
            '--email' => 'existing@pwa-store.com',
            '--username' => 'new_admin_unique',
        ])
            ->assertFailed();
    }
}
