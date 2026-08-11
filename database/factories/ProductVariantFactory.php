<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'sku' => 'CAT-'.fake()->unique()->numerify('######'), 'name' => 'A4 Print', 'options' => ['size' => 'A4'], 'price_minor' => 1999, 'currency' => 'GBP', 'is_active' => true, 'sort_order' => 0];
    }
}
