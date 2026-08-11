<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->active()->ordered()
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
            ->paginate(12);

        return view('storefront.products.index', compact('products'));
    }

    public function show(string $slug): View
    {
        $product = Product::query()->active()->where('slug', $slug)
            ->with([
                'images',
                'variants' => fn ($query) => $query->active()->ordered(),
                'artworkStyles',
                'recommendedArtworkStyle',
                'personalisationFields',
            ])->firstOrFail();

        $recommendedStyle = $product->artworkStyles->firstWhere('id', $product->recommended_artwork_style_id)
            ?? $product->artworkStyles->first();

        $defaultOptions = $product->preview_configuration['default_variant_options'] ?? [];
        $defaultVariant = $product->variants->first(fn ($variant) => collect($defaultOptions)->every(
            fn ($value, $key) => ($variant->options[$key] ?? null) === $value
        )) ?? $product->variants->first();
        $defaultImage = $product->images->firstWhere('product_variant_id', $defaultVariant?->id)
            ?? $product->primaryImage();

        return view('storefront.products.show', compact('product', 'recommendedStyle', 'defaultVariant', 'defaultImage'));
    }
}
