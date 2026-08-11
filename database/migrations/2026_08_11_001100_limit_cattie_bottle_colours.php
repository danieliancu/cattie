<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $productId = DB::table('products')->where('slug', 'cattie-water-bottle')->value('id');

        if (! $productId) {
            return;
        }

        DB::table('product_variants')->where('product_id', $productId)->get()->each(function ($variant): void {
            $colour = json_decode($variant->options, true)['colour'] ?? null;
            $allowed = in_array($colour, ['black', 'grey', 'navy', 'red'], true);

            DB::table('product_variants')->where('id', $variant->id)->update([
                'name' => $colour === 'grey' ? 'Gray · 650 ml' : $variant->name,
                'is_active' => $allowed,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $productId = DB::table('products')->where('slug', 'cattie-water-bottle')->value('id');

        if ($productId) {
            DB::table('product_variants')->where('product_id', $productId)->update(['is_active' => true, 'updated_at' => now()]);
        }
    }
};
