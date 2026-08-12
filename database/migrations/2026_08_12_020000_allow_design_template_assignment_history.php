<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_template_assignments', function (Blueprint $table): void {
            $table->dropUnique(['product_id', 'is_active']);
            $table->dropUnique(['product_variant_id', 'is_active']);
            $table->index(['product_id', 'is_active']);
            $table->index(['product_variant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('design_template_assignments', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'is_active']);
            $table->dropIndex(['product_variant_id', 'is_active']);
            $table->unique(['product_id', 'is_active']);
            $table->unique(['product_variant_id', 'is_active']);
        });
    }
};
