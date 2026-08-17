<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\CatalogueNavigation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SitemapController extends Controller
{
    /**
     * The HTML sitemap doubles as a customer navigation page, so it shows the
     * whole active hierarchy including subcategories that have no products yet.
     */
    public function index(): View
    {
        $categories = CatalogueNavigation::topLevelWithChildren();
        $products = Product::query()->active()->ordered()->get();

        return view('storefront.sitemap-page', compact('categories', 'products'));
    }

    /**
     * The XML sitemap is for indexing, so it is stricter: a subcategory only
     * appears once it has at least one active product. That matches the
     * noindex rule on empty collections, and reverses by itself as soon as
     * products are assigned.
     */
    public function xml(): Response
    {
        $categories = CatalogueNavigation::topLevelWithChildren();

        // One query for every category that currently holds an active product,
        // rather than an existence check per subcategory.
        $populated = DB::table('product_category')
            ->join('products', 'products.id', '=', 'product_category.product_id')
            ->where('products.is_active', true)
            ->where('products.status', ProductStatus::Published->value)
            ->whereNull('products.deleted_at')
            ->distinct()
            ->pluck('product_category.product_category_id')
            ->flip();

        $urls = $categories
            ->flatMap(fn ($category) => [$category, ...$category->children])
            ->filter(fn ($category) => $category->isTopLevel() || $populated->has($category->getKey()))
            ->map(fn ($category) => ['loc' => $category->url(), 'lastmod' => $category->updated_at])
            ->values();

        $products = Product::query()->active()->ordered()->get();
        $staticUrls = [
            route('home'), route('products.index'), route('sitemap'), route('information.terms'), route('information.faq'),
            route('information.delivery'), route('information.returns'), route('information.privacy'), route('information.cookies'), route('information.payments'),
        ];

        return response()
            ->view('storefront.sitemap', compact('urls', 'products', 'staticUrls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
