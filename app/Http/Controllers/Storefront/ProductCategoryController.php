<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Support\CanonicalUrl;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function show(ProductCategory $category): View
    {
        abort_unless($category->is_active, 404);

        $products = $category->products()
            ->active()
            ->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])
            ->paginate(12);
        $canonical = CanonicalUrl::forPaginator(route('categories.show', $category), $products);

        return view('storefront.categories.show', compact('category', 'products', 'canonical'));
    }
}
