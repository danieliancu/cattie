<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $productId = DB::table('products')->where('slug', 'cattie-water-bottle')->value('id');

        if ($productId) {
            DB::table('product_personalisation_fields')
                ->where('product_id', $productId)
                ->where('key', 'name')
                ->update(['validation_rules' => json_encode(['max' => 12], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $productId = DB::table('products')->where('slug', 'cattie-water-bottle')->value('id');

        if ($productId) {
            DB::table('product_personalisation_fields')
                ->where('product_id', $productId)
                ->where('key', 'name')
                ->update(['validation_rules' => json_encode(['max' => 30], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        }
    }
};
