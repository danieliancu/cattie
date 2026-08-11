<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composed_designs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('artwork_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('generation_asset_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_design_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('template_version');
            $table->json('personalisation_snapshot');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('format', 20);
            $table->string('disk')->default('local');
            $table->string('storage_key')->unique();
            $table->string('preview_storage_key')->unique();
            $table->string('status')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['artwork_session_id', 'created_at']);
        });

        Schema::table('artwork_sessions', fn (Blueprint $table) => $table->foreignUlid('approved_composed_design_id')->nullable()->constrained('composed_designs')->nullOnDelete());
        Schema::table('cart_items', fn (Blueprint $table) => $table->foreignUlid('composed_design_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('order_items', fn (Blueprint $table) => $table->foreignUlid('composed_design_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('composed_design_id'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('composed_design_id'));
        Schema::table('artwork_sessions', fn (Blueprint $table) => $table->dropConstrainedForeignId('approved_composed_design_id'));
        Schema::dropIfExists('composed_designs');
    }
};
