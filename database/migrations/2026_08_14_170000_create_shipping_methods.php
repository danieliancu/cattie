<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider');
            $table->string('code');
            $table->string('name');
            $table->string('provider_service_code');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('GBP');
            $table->char('country', 2)->default('GB');
            $table->unsignedSmallInteger('estimated_business_days_min');
            $table->unsignedSmallInteger('estimated_business_days_max');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['provider', 'code']);
            $table->index(['provider', 'country', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('shipping_method_id')->nullable()->after('shipping_address')->constrained('shipping_methods')->nullOnDelete();
            $table->json('shipping_method_snapshot')->nullable()->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('shipping_method_snapshot');
        });
        Schema::dropIfExists('shipping_methods');
    }
};
