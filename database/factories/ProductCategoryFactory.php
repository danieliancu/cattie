<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'short_description' => fake()->sentence(),
            'description' => null,
            'meta_title' => null,
            'meta_description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
