<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite implements an added foreign key as a full table rebuild guarded by
     * `PRAGMA foreign_keys = 0/1`, and that pragma is a silent no-op inside a
     * transaction. Left in a transaction, the intermediate `drop table` would
     * cascade through `product_category` and wipe every product/category pivot
     * row on an already-populated database.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignUlid('parent_id')->nullable()->after('slug')
                ->constrained('product_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['parent_id', 'sort_order'], 'product_categories_parent_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex('product_categories_parent_sort_index');
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
