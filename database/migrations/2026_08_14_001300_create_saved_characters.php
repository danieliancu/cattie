<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_characters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('artwork_style_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('source_generation_asset_id')->nullable()->constrained('generation_assets')->nullOnDelete();
            $table->string('name', 100);
            $table->string('disk')->default('local');
            $table->string('storage_key')->unique();
            $table->string('preview_storage_key')->unique();
            $table->string('mime_type', 100)->default('image/png');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->unique(['user_id', 'artwork_style_id', 'sha256'], 'saved_characters_owner_style_hash_unique');
        });

        Schema::table('generations', function (Blueprint $table) {
            $table->dropForeign(['upload_id']);
            $table->foreignUlid('saved_character_id')->nullable()->after('upload_id')->constrained('saved_characters')->nullOnDelete();
        });
        Schema::table('generations', function (Blueprint $table) {
            $table->ulid('upload_id')->nullable()->change();
            $table->foreign('upload_id')->references('id')->on('uploads')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropForeign(['upload_id']);
            $table->dropConstrainedForeignId('saved_character_id');
        });
        Schema::table('generations', function (Blueprint $table) {
            $table->ulid('upload_id')->nullable(false)->change();
            $table->foreign('upload_id')->references('id')->on('uploads')->restrictOnDelete();
        });
        Schema::dropIfExists('saved_characters');
    }
};
