<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `orders.shipping_address` and `customer_profiles.default_shipping_address`
 * are cast as `encrypted:array`, so the stored value is an opaque encrypted
 * string, not JSON. They were created as `json` columns, which SQLite accepts
 * (JSON is just TEXT there) but MySQL rejects with SQLSTATE[22032] "Invalid JSON
 * text". Store them as TEXT so the ciphertext is accepted on MySQL too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('shipping_address')->change();
        });
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->text('default_shipping_address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('shipping_address')->change();
        });
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->json('default_shipping_address')->nullable()->change();
        });
    }
};
