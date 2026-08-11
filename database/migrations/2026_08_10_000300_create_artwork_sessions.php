<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artwork_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('public_id', 26)->unique();
            $table->string('access_token_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('artwork_style_id')->constrained()->restrictOnDelete();
            $table->json('personalisation_snapshot');
            $table->string('status')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('upload_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('upload_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('disk');
            $table->string('storage_key')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
        });

        Schema::table('uploads', function (Blueprint $table) {
            $table->foreignUlid('artwork_session_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
        Schema::table('generations', function (Blueprint $table) {
            $table->foreignUlid('artwork_session_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('quality')->nullable();
            $table->string('output_size')->nullable();
            $table->unsignedSmallInteger('candidate_count')->default(1);
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('provider_request_id')->nullable()->index();
            $table->string('provider_job_id')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('failure_category')->nullable();
            $table->string('provider_error_code')->nullable();
            $table->boolean('is_retryable')->default(false);
            $table->json('usage_metadata')->nullable();
            $table->string('pricing_version')->nullable();
            $table->string('cost_basis')->nullable();
        });
        Schema::table('artwork_sessions', function (Blueprint $table) {
            $table->foreignUlid('current_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->foreignUlid('current_generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->foreignUlid('approved_generation_asset_id')->nullable()->constrained('generation_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artwork_sessions', fn (Blueprint $table) => $table->dropForeign(['current_upload_id']));
        Schema::table('artwork_sessions', fn (Blueprint $table) => $table->dropForeign(['current_generation_id']));
        Schema::table('artwork_sessions', fn (Blueprint $table) => $table->dropForeign(['approved_generation_asset_id']));
        Schema::table('generations', fn (Blueprint $table) => $table->dropForeign(['artwork_session_id']));
        Schema::table('uploads', fn (Blueprint $table) => $table->dropForeign(['artwork_session_id']));
        Schema::dropIfExists('upload_assets');
        Schema::dropIfExists('artwork_sessions');
    }
};
