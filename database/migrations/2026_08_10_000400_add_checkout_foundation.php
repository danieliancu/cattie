<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('access_token_hash', 64)->nullable()->index();
            $table->string('pricing_hash', 64)->nullable();
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignUlid('artwork_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name')->nullable();
            $table->string('variant_name')->nullable();
            $table->string('artwork_style_name')->nullable();
            $table->unique('artwork_session_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('access_token_hash', 64)->nullable()->index();
            $table->uuid('checkout_idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->string('shipping_status')->default('unresolved');
            $table->string('tax_status')->default('unresolved');
            $table->string('totals_status')->default('unresolved')->index();
            $table->boolean('is_payable')->default(false)->index();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('shipping_minor')->nullable()->change();
            $table->unsignedBigInteger('tax_minor')->nullable()->change();
            $table->unsignedBigInteger('total_minor')->nullable()->change();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUlid('artwork_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('artwork_style_name')->nullable();
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignUlid('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_order_id');
            $table->dropColumn(['access_token_hash', 'pricing_hash']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['artwork_session_id']);
            $table->dropConstrainedForeignId('artwork_session_id');
            $table->dropConstrainedForeignId('generation_id');
            $table->dropColumn(['product_name', 'variant_name', 'artwork_style_name']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['access_token_hash', 'checkout_idempotency_key', 'discount_minor', 'shipping_status', 'tax_status', 'totals_status', 'is_payable', 'phone']));
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('artwork_session_id');
            $table->dropConstrainedForeignId('generation_id');
            $table->dropColumn('artwork_style_name');
        });
    }
};
