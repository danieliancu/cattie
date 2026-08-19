<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the replacement non-unique indexes first so the foreign keys on
        // product_id / product_variant_id keep a supporting index. MySQL refuses
        // to drop an index that a foreign key still relies on (error 1553), so
        // the new index must exist before the unique one is dropped.
        Schema::table('design_template_assignments', function (Blueprint $table): void {
            $table->index(['product_id', 'is_active']);
            $table->index(['product_variant_id', 'is_active']);
        });
        Schema::table('design_template_assignments', function (Blueprint $table): void {
            $table->dropUnique(['product_id', 'is_active']);
            $table->dropUnique(['product_variant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        // Re-create the unique indexes before dropping the plain ones, for the
        // same foreign-key reason as above.
        Schema::table('design_template_assignments', function (Blueprint $table): void {
            $table->unique(['product_id', 'is_active']);
            $table->unique(['product_variant_id', 'is_active']);
        });
        Schema::table('design_template_assignments', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'is_active']);
            $table->dropIndex(['product_variant_id', 'is_active']);
        });
    }
};
