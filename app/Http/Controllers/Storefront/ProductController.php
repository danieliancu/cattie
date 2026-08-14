<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\ResolveResumableArtworkSession;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSlugRedirect;
use App\Support\CanonicalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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
        $categories = ProductCategory::query()->active()
            ->whereHas('products', fn ($query) => $query->active())
            ->ordered()->get();
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
                'categories' => fn ($query) => $query->active(),
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
        $savedCharacters = collect();
        $currentSavedCharacter = null;
        if ($request->user()) {
            $savedCharacters = $request->user()->savedCharacters()
                ->with('artworkStyle')
                ->whereIn('artwork_style_id', $product->artworkStyles->pluck('id'))
                ->latest()
                ->get();
            $currentAsset = $session?->currentGeneration?->assets?->firstWhere('kind', 'composition_source');
            $currentSavedCharacter = $session?->currentGeneration?->savedCharacter;
            if (! $currentSavedCharacter && $currentAsset && Storage::disk($currentAsset->disk)->exists($currentAsset->storage_key)) {
                $currentSavedCharacter = $savedCharacters->first(fn ($character) => $character->artwork_style_id === $session->artwork_style_id
                    && hash_equals($character->sha256, hash_file('sha256', Storage::disk($currentAsset->disk)->path($currentAsset->storage_key))));
            }
            if ($currentSavedCharacter) {
                $savedCharacters = $savedCharacters->reject(fn ($character) => $character->is($currentSavedCharacter))->values();
            }
        }
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
            ->view('storefront.products.show', compact('product', 'recommendedStyle', 'defaultVariant', 'defaultImage', 'session', 'relatedProducts', 'savedCharacters', 'currentSavedCharacter'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
