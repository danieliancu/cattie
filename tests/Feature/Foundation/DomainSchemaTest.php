<?php

namespace Tests\Feature\Foundation;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_variants_and_uses_integer_minor_unit_prices(): void
    {
        $product = Product::factory()->create(['base_price_minor' => 2499]);
        $variant = ProductVariant::factory()->for($product)->create(['price_minor' => 2999]);

        $this->assertTrue($product->variants->first()->is($variant));
        $this->assertSame(2999, $variant->price_minor);
        $this->assertSame('GBP', $variant->currency);
    }

    public function test_webhook_provider_event_identity_is_idempotent(): void
    {
        $event = ['provider' => 'test', 'external_event_id' => 'evt_1', 'event_type' => 'paid', 'payload' => [], 'status' => 'received', 'received_at' => now()];
        WebhookEvent::query()->create($event);
        $this->expectException(QueryException::class);
        WebhookEvent::query()->create($event);
    }
}
