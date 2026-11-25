<?php

namespace Database\Factories;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\Pwa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pwa>
 */
class PwaFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name).'-'.fake()->unique()->randomNumber(4);

        return [
            'developer_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => ucfirst($name),
            'slug' => $slug,
            'short_description' => fake('ar_SA')->realText(80),
            'description' => fake('ar_SA')->realText(300),
            'manifest_url' => "https://{$slug}.example.com/manifest.json",
            'start_url' => "https://{$slug}.example.com/",
            'icon_url' => 'https://api.iconify.design/lucide:app-window.svg',
            'screenshots' => [
                "https://picsum.photos/seed/{$slug}1/1080/1920",
                "https://picsum.photos/seed/{$slug}2/1080/1920",
            ],
            'version' => '1.0.0',
            'status' => PwaStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'is_published' => true,
            'install_count' => fake()->numberBetween(0, 500),
            'rating_average' => fake()->randomFloat(2, 3, 5),
            'review_count' => fake()->numberBetween(0, 50),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PwaStatus::Pending,
            'is_published' => false,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PwaStatus::Approved,
            'is_published' => true,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PwaStatus::Rejected,
            'is_published' => false,
            'reviewed_at' => now(),
            'rejection_reason' => fake('ar_SA')->realText(100),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PwaStatus::Draft,
            'is_published' => false,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }
}
