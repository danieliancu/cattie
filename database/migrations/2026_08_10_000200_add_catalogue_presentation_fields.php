<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('slug');
            $table->string('meta_description', 160)->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->index()->after('is_active');
            $table->foreignUlid('recommended_artwork_style_id')->nullable()->after('preview_configuration')->constrained('artwork_styles')->nullOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->index()->after('is_active');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('disk')->default('public')->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('disk'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('sort_order'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recommended_artwork_style_id');
            $table->dropColumn(['short_description', 'meta_description', 'sort_order']);
        });
    }
};
