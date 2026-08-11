<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artwork_styles', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('prompt_key');
            $t->unsignedInteger('prompt_version')->default(1);
            $t->json('configuration')->nullable();
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description');
            $t->boolean('is_active')->default(false)->index();
            $t->unsignedBigInteger('base_price_minor');
            $t->char('currency', 3)->default('GBP');
            $t->json('artwork_requirements')->nullable();
            $t->json('preview_configuration')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('artwork_style_product', function (Blueprint $t) {
            $t->foreignUlid('artwork_style_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['artwork_style_id', 'product_id']);
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $t->string('sku')->unique();
            $t->string('name');
            $t->json('options');
            $t->unsignedBigInteger('price_minor');
            $t->char('currency', 3)->default('GBP');
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $t->string('storage_key');
            $t->string('alt_text');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('product_personalisation_fields', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('label');
            $t->string('type');
            $t->boolean('is_required')->default(false);
            $t->json('validation_rules')->nullable();
            $t->json('configuration')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['product_id', 'key']);
        });
        Schema::create('fulfilment_product_mappings', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_variant_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->string('provider_sku');
            $t->json('configuration')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['product_variant_id', 'provider']);
        });
        Schema::create('uploads', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('guest_token')->nullable()->index();
            $t->string('disk');
            $t->string('storage_key')->unique();
            $t->string('original_name')->nullable();
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->string('sha256', 64)->index();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('generations', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('upload_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUlid('artwork_style_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('parent_generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $t->string('prompt_key');
            $t->unsignedInteger('prompt_version');
            $t->text('resolved_prompt');
            $t->string('provider');
            $t->string('model');
            $t->string('status')->index();
            $t->json('parameters')->nullable();
            $t->unsignedBigInteger('cost_micros')->nullable();
            $t->char('cost_currency', 3)->default('GBP');
            $t->text('failure_reason')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('generation_assets', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('generation_id')->constrained()->cascadeOnDelete();
            $t->string('kind');
            $t->string('disk');
            $t->string('storage_key')->unique();
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->boolean('is_selected')->default(false)->index();
            $t->timestamp('selected_at')->nullable();
            $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('guest_token')->nullable()->unique();
            $t->string('status')->default('active')->index();
            $t->char('currency', 3)->default('GBP');
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUlid('generation_asset_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('personalisation');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price_minor');
            $t->char('currency', 3);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('number')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('email');
            $t->string('status')->index();
            $t->char('currency', 3);
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('shipping_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->json('shipping_address');
            $t->timestamp('placed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUlid('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUlid('generation_asset_id')->nullable()->constrained()->nullOnDelete();
            $t->string('product_name');
            $t->string('variant_name')->nullable();
            $t->string('sku');
            $t->json('personalisation');
            $t->json('artwork_snapshot')->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('total_price_minor');
            $t->char('currency', 3);
            $t->timestamps();
        });
        Schema::create('order_status_transitions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['order_id', 'created_at']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->string('external_id')->nullable();
            $t->string('idempotency_key')->unique();
            $t->string('status')->index();
            $t->unsignedBigInteger('amount_minor');
            $t->char('currency', 3);
            $t->text('failure_reason')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'external_id']);
        });
        Schema::create('print_assets', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_item_id')->constrained()->cascadeOnDelete();
            $t->string('status')->index();
            $t->string('disk')->nullable();
            $t->string('storage_key')->nullable()->unique();
            $t->json('specification');
            $t->string('checksum', 64)->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('fulfilment_submissions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->string('idempotency_key')->unique();
            $t->string('external_order_id')->nullable();
            $t->string('status')->index();
            $t->json('request_payload')->nullable();
            $t->json('response_payload')->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'external_order_id']);
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('fulfilment_submission_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider');
            $t->string('external_id')->nullable();
            $t->string('status')->index();
            $t->string('carrier')->nullable();
            $t->string('tracking_number')->nullable();
            $t->string('tracking_url')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'external_id']);
        });
        Schema::create('webhook_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('provider');
            $t->string('external_event_id');
            $t->string('event_type');
            $t->json('payload');
            $t->string('status')->index();
            $t->timestamp('received_at');
            $t->timestamp('processed_at')->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'external_event_id']);
        });
        Schema::create('analytics_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name')->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('session_token')->nullable()->index();
            $t->nullableUlidMorphs('subject');
            $t->json('properties')->nullable();
            $t->timestamp('occurred_at')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['analytics_events', 'webhook_events', 'shipments', 'fulfilment_submissions', 'print_assets', 'payments', 'order_status_transitions', 'order_items', 'orders', 'cart_items', 'carts', 'generation_assets', 'generations', 'uploads', 'fulfilment_product_mappings', 'product_personalisation_fields', 'product_images', 'product_variants', 'artwork_style_product', 'products', 'artwork_styles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
