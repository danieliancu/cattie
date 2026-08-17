<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\CanonicalUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    private const SORTS = ['', 'price-high-low', 'price-low-high', 'under-20', 'over-20'];

    /**
     * Top-level category landing page: /{category-slug}
     *
     * Its job is to move the customer on to a subcategory, so the child list is
     * the main content. Featured products are aggregated from the children only
     * when some actually exist.
     */
    public function category(string $categorySlug): View
    {
        $category = ProductCategory::query()->active()->topLevel()
            ->where('slug', $categorySlug)
            ->with(['children' => fn ($query) => $query->active()->ordered()])
            ->first();

        abort_if($category === null, 404);

        $category->children->each(fn (ProductCategory $child) => $child->setRelation('parent', $category));

        $featuredProducts = $category->children->isEmpty()
            ? collect()
            : Product::query()->active()
                ->whereHas('categories', fn ($query) => $query->whereIn('product_categories.id', $category->children->modelKeys()))
                ->ordered()
                ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
                ->limit(8)
                ->get();

        return view('storefront.categories.category', [
            'category' => $category,
            'featuredProducts' => $featuredProducts,
            'canonical' => $category->url(),
        ]);
    }

    /**
     * Subcategory / SEO collection page: /{category-slug}/{subcategory-slug}
     *
     * The child is resolved *through* its parent, so a slug that exists under a
     * different parent can never resolve here — it 404s rather than redirecting.
     */
    public function subcategory(string $categorySlug, string $subcategorySlug, Request $request): View
    {
        $parent = ProductCategory::query()->active()->topLevel()
            ->where('slug', $categorySlug)
            ->first();

        abort_if($parent === null, 404);

        $category = $parent->children()->active()->where('slug', $subcategorySlug)->first();

        abort_if($category === null, 404);

        $category->setRelation('parent', $parent);

        $sort = $request->string('sort')->toString();
        abort_unless(in_array($sort, self::SORTS, true), 404);

        $query = $category->products()
            ->active()
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->from('product_variants')
                    ->selectRaw('MIN(COALESCE(price_override_minor, price_minor))')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.is_active', true);
            }, 'catalogue_price_minor')
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()]);

        match ($sort) {
            'price-high-low' => $query->orderByDesc('catalogue_price_minor'),
            'price-low-high' => $query->orderBy('catalogue_price_minor'),
            'under-20' => $query->having('catalogue_price_minor', '<', 2000),
            'over-20' => $query->having('catalogue_price_minor', '>', 2000),
            default => null,
        };

        $products = $query->paginate(12)->withQueryString();

        $siblings = $parent->children()->active()->ordered()
            ->whereKeyNot($category->getKey())
            ->get()
            ->each(fn (ProductCategory $sibling) => $sibling->setRelation('parent', $parent));

        return view('storefront.categories.show', [
            'category' => $category,
            'products' => $products,
            'siblings' => $siblings,
            'sort' => $sort,
            'canonical' => CanonicalUrl::forPaginator($category->url(), $products),
            // A collection with nothing in it is a thin page: publish it for
            // customers, but keep it out of the index until it has stock. This
            // reverses automatically the moment a product is assigned.
            'robots' => $products->total() === 0 ? 'noindex,follow' : null,
        ]);
    }
}
