<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\ResolveResumableArtworkSession;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $products = Product::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->active()->ordered()
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
            ->paginate(12)
            ->withQueryString();

        return view('storefront.products.index', compact('products', 'search'));
    }

    public function show(string $slug, Request $request, ResolveResumableArtworkSession $resolve): Response
    {
        $product = Product::query()->active()->where('slug', $slug)
            ->with([
                'images',
                'designTemplate',
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
        $session = $resolve->handle($product, $request);
        $relatedProducts = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->ordered()
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
            ->limit(4)
            ->get();

        return response()
            ->view('storefront.products.show', compact('product', 'recommendedStyle', 'defaultVariant', 'defaultImage', 'session', 'relatedProducts'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
