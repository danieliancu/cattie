<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Support\CanonicalUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function show(ProductCategory $category, Request $request): View
    {
        abort_unless($category->is_active, 404);

        $sort = $request->string('sort')->toString();
        abort_unless(in_array($sort, ['', 'price-high-low', 'price-low-high', 'under-20', 'over-20'], true), 404);

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
        $canonical = CanonicalUrl::forPaginator(route('categories.show', $category), $products);

        return view('storefront.categories.show', compact('category', 'products', 'canonical', 'sort'));
    }
}
