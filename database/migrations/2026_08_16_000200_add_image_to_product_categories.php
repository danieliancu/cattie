<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            // Same disk/storage_key convention as product_images.
            $table->string('image_disk')->nullable()->after('description');
            $table->string('image_storage_key')->nullable()->after('image_disk');
            $table->string('image_alt_text')->nullable()->after('image_storage_key');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['image_disk', 'image_storage_key', 'image_alt_text']);
        });
    }
};
