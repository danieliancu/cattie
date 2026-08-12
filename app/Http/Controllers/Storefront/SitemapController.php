<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SitemapController extends Controller
{
    public function index(): View
    {
        $categories = $this->categories()->load(['products' => fn ($query) => $query->active()]);
        $products = Product::query()->active()->ordered()->get();

        return view('storefront.sitemap-page', compact('categories', 'products'));
    }

    public function xml(): Response
    {
        $categories = $this->categories();
        $products = Product::query()->active()->ordered()->get();
        $staticUrls = [
            route('home'), route('products.index'), route('sitemap'), route('information.terms'), route('information.faq'),
            route('information.delivery'), route('information.returns'), route('information.privacy'), route('information.payments'),
        ];

        return response()
            ->view('storefront.sitemap', compact('categories', 'products', 'staticUrls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function categories()
    {
        return ProductCategory::query()->active()
            ->whereHas('products', fn ($query) => $query->active())
            ->ordered()->get();
    }
}
