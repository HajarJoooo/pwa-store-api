<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'name_ar' => fake('ar_SA')->realText(15),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(3),
            'description' => fake('ar_SA')->realText(120),
            'icon_url' => 'https://api.iconify.design/lucide:folder.svg',
            'parent_id' => null,
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
