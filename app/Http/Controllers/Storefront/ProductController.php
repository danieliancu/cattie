<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\ResolveResumableArtworkSession;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSlugRedirect;
use App\Support\CanonicalUrl;
use App\Support\CatalogueNavigation;
use Illuminate\Http\RedirectResponse;
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
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('categories', fn ($query) => $query->active()->where(function ($query) use ($search) {
                        $query->where('name', 'like', '%'.$search.'%')
                            ->orWhere('short_description', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    }));
            }))
            ->active()->ordered()
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
            ->paginate(12)
            ->withQueryString();
        // The four top-level categories, shown regardless of current stock so the
        // structure is browsable before the catalogue is fully imported.
        $categories = CatalogueNavigation::topLevelWithChildren();
        $canonical = $search === '' ? CanonicalUrl::forPaginator(route('products.index'), $products) : route('products.index');
        $robots = $search !== '' ? 'noindex,follow' : null;

        return view('storefront.products.index', compact('products', 'search', 'categories', 'canonical', 'robots'));
    }

    public function show(string $slug, Request $request, ResolveResumableArtworkSession $resolve): Response|RedirectResponse
    {
        $product = Product::query()->active()->where('slug', $slug)
            ->with([
                'images',
                'designTemplate',
                'variants' => fn ($query) => $query->active()->ordered()->with('fulfilmentMappings'),
                'artworkStyles',
                'recommendedArtworkStyle',
                'personalisationFields',
                // The parent is needed so each category link can build its nested URL.
                'categories' => fn ($query) => $query->active()->with('parent:id,slug'),
            ])->first();
        if (! $product) {
            $redirect = ProductSlugRedirect::with('product')->where('old_slug', $slug)->first();
            abort_unless($redirect?->product?->status === ProductStatus::Published, 404);

            return redirect()->route('products.show', $redirect->product->slug, 301);
        }

        $recommendedStyle = $product->artworkStyles->firstWhere('id', $product->recommended_artwork_style_id);

        $defaultOptions = $product->preview_configuration['default_variant_options'] ?? [];
        $availableVariants = $product->variants->filter(fn ($variant) => ! $product->designTemplate || $variant->hasSingleActiveFulfilmentMapping());
        $defaultVariant = $availableVariants->first(fn ($variant) => collect($defaultOptions)->every(
            fn ($value, $key) => ($variant->options[$key] ?? null) === $value
        )) ?? $availableVariants->first();
        $defaultImage = $product->images->firstWhere('product_variant_id', $defaultVariant?->id)
            ?? $product->primaryImage();
        $session = $resolve->handle($product, $request);
        $relatedProducts = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->whereHas('categories', fn ($query) => $query->active()->whereIn('product_categories.id', $product->categories->pluck('id')))
            ->ordered()
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
            ->limit(4)
            ->get();
        if ($relatedProducts->count() < 4) {
            $relatedProducts = $relatedProducts->concat(
                Product::query()->active()
                    ->whereNotIn('id', $relatedProducts->pluck('id')->push($product->id))
                    ->ordered()
                    ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
                    ->limit(4 - $relatedProducts->count())
                    ->get()
            );
        }

        return response()
            ->view('storefront.products.show', compact('product', 'recommendedStyle', 'defaultVariant', 'defaultImage', 'session', 'relatedProducts'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
