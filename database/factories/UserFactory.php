<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $arFaker = fake('ar_SA');

        return [
            'first_name' => $arFaker->firstName(),
            'last_name' => $arFaker->lastName(),
            'username' => strtolower(fake()->unique()->userName()),
            'email' => strtolower(fake()->unique()->safeEmail()),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'bio' => 'مطور برمجيات ومهتم بتطبيقات الويب التقدمية.',
            'avatar_url' => 'https://ui-avatars.com/api/?name='.urlencode(fake()->name()).'&background=random',
            'country' => 'السعودية',
            'city' => 'الرياض',
            'is_verified' => true,
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
