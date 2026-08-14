<?php

namespace Tests\Feature\Commerce;

use App\Domain\Payments\Actions\ResolveEligibleShippingMethods;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_seed_is_idempotent_and_ordered_by_authoritative_price(): void
    {
        $this->seed(ShippingMethodSeeder::class);
        $this->seed(ShippingMethodSeeder::class);

        $this->assertDatabaseCount('shipping_methods', 3);
        $methods = ShippingMethod::query()->ordered()->get();
        $this->assertSame(['Royal Mail 48 Tracked', 'Royal Mail 24 Tracked', 'DPD'], $methods->pluck('name')->all());
        $this->assertSame([350, 413, 749], $methods->pluck('price_minor')->all());
        $this->assertSame(['5–8 business days', '4–7 business days', '4 business days'], $methods->map->estimateLabel()->all());
    }

    public function test_only_methods_for_the_single_common_fulfilment_provider_are_eligible(): void
    {
        $this->seed(ShippingMethodSeeder::class);
        $cart = Cart::query()->create(['status' => 'active', 'currency' => 'GBP']);
        $treatPod = $this->addItem($cart, 'treatpod', 'TP-1');

        $this->assertCount(3, app(ResolveEligibleShippingMethods::class)->handle($cart));

        $this->addItem($cart, 'prodigi', 'PG-1');
        $this->assertCount(0, app(ResolveEligibleShippingMethods::class)->handle($cart->fresh()));
        $this->assertSame('treatpod', $treatPod->fulfilmentMappings()->first()->provider);
    }

    private function addItem(Cart $cart, string $provider, string $providerSku): ProductVariant
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $variant->fulfilmentMappings()->create(['provider' => $provider, 'provider_sku' => $providerSku, 'configuration' => [], 'is_active' => true]);
        $cart->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'variant_name' => $variant->name, 'personalisation' => [], 'quantity' => 1, 'unit_price_minor' => $variant->price_minor, 'currency' => 'GBP']);

        return $variant;
    }
}
