<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductPreviewController extends Controller
{
    public function __invoke(Product $product, Request $request)
    {
        abort_unless($request->hasValidSignature() && $request->user()?->is_admin, 403);
        $product->load(['images', 'defaultVariant', 'variants' => fn ($q) => $q->active()->ordered()->with('fulfilmentMappings'), 'artworkStyles', 'recommendedArtworkStyle', 'personalisationFields', 'categories']);
        $recommendedStyle = $product->artworkStyles->firstWhere('id', $product->recommended_artwork_style_id);
        $defaultVariant = $product->variants->first(fn ($variant) => $variant->hasSingleActiveFulfilmentMapping());
        $defaultImage = $product->primaryImage();
        $session = null;
        $relatedProducts = collect();

        return response()->view('storefront.products.show', compact('product', 'recommendedStyle', 'defaultVariant', 'defaultImage', 'session', 'relatedProducts') + ['previewRobots' => 'noindex,nofollow'])->header('Cache-Control', 'private, no-store');
    }
}
