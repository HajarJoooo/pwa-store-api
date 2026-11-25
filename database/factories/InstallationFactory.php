<?php

namespace Database\Factories;

use App\Models\Installation;
use App\Models\Pwa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pwa_id' => Pwa::factory(),
            'installed_at' => now(),
        ];
    }
}
