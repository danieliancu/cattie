<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->boolean('is_product')->default(false)->index()->after('is_primary');
        });

        DB::table('product_images')->select('product_id')->distinct()->pluck('product_id')->each(function ($productId) {
            $imageId = DB::table('product_images')->where('product_id', $productId)->where('role', 'primary')->orderBy('sort_order')->value('id');
            if ($imageId) {
                DB::table('product_images')->where('id', $imageId)->update(['is_product' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('is_product'));
    }
};
