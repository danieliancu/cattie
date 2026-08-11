<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_design_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->unsignedInteger('version');
            $table->string('definition_path');
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignUlid('product_design_template_id')->nullable()->after('preview_configuration')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_design_template_id');
        });

        Schema::dropIfExists('product_design_templates');
    }
};
