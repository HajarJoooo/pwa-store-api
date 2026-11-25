<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    protected static ?string $hashedPassword = null;

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::unguard();

        $defaultPassword = env('SEEDER_DEFAULT_PASSWORD', 'Password123!');
        $hashedPassword = static::$hashedPassword ??= Hash::make($defaultPassword);

        // 1. Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@pwastore.com'],
            [
                'first_name' => 'مدير',
                'last_name' => 'النظام',
                'username' => 'admin',
                'password' => $hashedPassword,
                'phone' => '+966500000001',
                'bio' => 'مدير عام متجر PWA Store',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Admin&background=0D8ABC&color=fff',
                'country' => 'السعودية',
                'city' => 'الرياض',
                'is_verified' => true,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // 2. Moderator
        $moderator = User::firstOrCreate(
            ['email' => 'moderator@pwastore.com'],
            [
                'first_name' => 'مشرف',
                'last_name' => 'التطبيقات',
                'username' => 'moderator',
                'password' => $hashedPassword,
                'phone' => '+966500000002',
                'bio' => 'مشرف مراجعة وفحص التطبيقات المقدمة',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Moderator&background=F59E0B&color=fff',
                'country' => 'مصر',
                'city' => 'القاهرة',
                'is_verified' => true,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $moderator->syncRoles(['moderator']);

        // 3. Three Developers
        $developers = [
            [
                'first_name' => 'أحمد',
                'last_name' => 'المطور',
                'username' => 'ahmed_dev',
                'email' => 'ahmed@pwastore.com',
                'bio' => 'مطور ويب متخصص في بناء تطبيقات PWA متقدمة وسريعة.',
            ],
            [
                'first_name' => 'سارة',
                'last_name' => 'التقنية',
                'username' => 'sara_dev',
                'email' => 'sara@pwastore.com',
                'bio' => 'مهندسة برمجيات شغوفة بالواجهات التقدمية وتجربة المستخدم.',
            ],
            [
                'first_name' => 'طارق',
                'last_name' => 'البرمجي',
                'username' => 'tariq_dev',
                'email' => 'tariq@pwastore.com',
                'bio' => 'مطور مستقل ومحب للتقنيات المفتوحة المصدر وتطبيقات الويب الحديثة.',
            ],
        ];

        foreach ($developers as $devData) {
            $dev = User::firstOrCreate(
                ['email' => $devData['email']],
                [
                    'first_name' => $devData['first_name'],
                    'last_name' => $devData['last_name'],
                    'username' => $devData['username'],
                    'password' => $hashedPassword,
                    'phone' => '+9665000000'.rand(10, 99),
                    'bio' => $devData['bio'],
                    'avatar_url' => 'https://ui-avatars.com/api/?name='.urlencode($devData['first_name']).'&background=10B981&color=fff',
                    'country' => 'السعودية',
                    'city' => 'جدة',
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $dev->syncRoles(['developer']);
        }

        // 4. Ten Normal Users
        for ($i = 1; $i <= 10; $i++) {
            $user = User::firstOrCreate(
                ['email' => "user{$i}@pwastore.com"],
                [
                    'first_name' => 'مستخدم',
                    'last_name' => "{$i}",
                    'username' => "user_{$i}",
                    'password' => $hashedPassword,
                    'phone' => '+9665100000'.sprintf('%02d', $i),
                    'bio' => 'مستخدم نشط لتطبيقات الويب في متجر PWA Store',
                    'avatar_url' => "https://ui-avatars.com/api/?name=User+{$i}&background=6366F1&color=fff",
                    'country' => 'السعودية',
                    'city' => 'الرياض',
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->syncRoles(['user']);
        }

        User::reguard();
    }
}
