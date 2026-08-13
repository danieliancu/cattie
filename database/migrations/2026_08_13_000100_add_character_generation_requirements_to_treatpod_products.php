<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUGS = [
        'water-bottle-with-red-flip-lid',
        'small-plastic-lunchbox',
        'personalised-stationery-pencil-tin',
    ];

    public function up(): void
    {
        DB::table('products')->whereIn('slug', self::SLUGS)->get(['id', 'artwork_requirements'])->each(function ($product): void {
            $existing = json_decode($product->artwork_requirements ?: '[]', true) ?: [];
            DB::table('products')->where('id', $product->id)->update([
                'artwork_requirements' => json_encode(array_merge($existing, [
                    'source_photo' => 'required',
                    'orientation' => 'portrait',
                    'framing' => 'full_body',
                    'isolated_subject' => true,
                    'transparent_background' => true,
                ]), JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('products')->whereIn('slug', self::SLUGS)->get(['id', 'artwork_requirements'])->each(function ($product): void {
            $existing = json_decode($product->artwork_requirements ?: '[]', true) ?: [];
            foreach (['framing', 'isolated_subject', 'transparent_background'] as $key) {
                unset($existing[$key]);
            }
            $existing['orientation'] = 'portrait_preferred';
            DB::table('products')->where('id', $product->id)->update([
                'artwork_requirements' => json_encode($existing, JSON_THROW_ON_ERROR),
            ]);
        });
    }
};
