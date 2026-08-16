<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_support_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('contact_email');
            $table->text('message');
            $table->string('status')->default('open');
            $table->string('photo_disk')->nullable();
            $table->string('photo_storage_key')->nullable();
            $table->string('photo_mime_type')->nullable();
            $table->unsignedBigInteger('photo_size_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_support_requests');
    }
};
