<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['name' => ucwords($name), 'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4), 'short_description' => fake()->sentence(), 'description' => fake()->paragraph(), 'meta_description' => fake()->sentence(), 'is_active' => true, 'sort_order' => 0, 'base_price_minor' => 1999, 'currency' => 'GBP', 'artwork_requirements' => ['aspect_ratio' => '4:5'], 'preview_configuration' => []];
    }
}
