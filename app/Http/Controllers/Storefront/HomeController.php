<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Support\CatalogueNavigation;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('storefront.home', [
            'featuredProducts' => Product::query()->active()->ordered()->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])->limit(8)->get(),
            'artworkStyles' => ArtworkStyle::query()->where('is_active', true)->orderBy('name')->get(),
            // The four top-level categories with featured products, shown whether or not they yet hold products.
            'categories' => CatalogueNavigation::attachFeaturedProducts(CatalogueNavigation::topLevelWithChildren(), limit: 8),
            'heroTiles' => $this->heroTiles(),
        ]);
    }

    /**
     * Six product tiles shown under the phone hero (3 per row, 2 rows). The first
     * three carry the product image; the rest are placeholders (pale circle only)
     * until their products and imagery are ready.
     *
     * @return array<int, array{name:string,url:?string,image:?string}>
     */
    private function heroTiles(): array
    {
        $config = [
            ['name' => 'Water Bottle with Red Flip Lid', 'withImage' => true],
            ['name' => 'Small Plastic Lunchbox', 'withImage' => true],
            ['name' => 'Personalised Stationery & Pencil Tin', 'withImage' => true],
            ['name' => 'Custom A3 Wall Print', 'withImage' => false],
            ['name' => 'Personalised School Backpack', 'withImage' => false],
            ['name' => 'Personalised Pet Bowl', 'withImage' => false],
        ];

        $products = Product::query()->active()
            ->whereIn('name', array_column($config, 'name'))
            ->with('images')->get()->keyBy('name');

        return array_map(function (array $tile) use ($products) {
            $product = $products->get($tile['name']);

            return [
                'name' => $tile['name'],
                'url' => $product ? route('products.show', $product->slug) : null,
                'image' => $tile['withImage'] && $product ? $product->primaryImage()?->url() : null,
            ];
        }, $config);
    }
}
