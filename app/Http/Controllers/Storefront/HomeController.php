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
        // The first three carry a specific catalogue image (by filename); the rest
        // are pale-circle placeholders until their products and imagery are ready.
        $config = [
            ['name' => 'Water Bottle with Red Flip Lid', 'image' => 'anna-product-2.png'],
            ['name' => 'Small Plastic Lunchbox', 'image' => '7becfb33-e340-41a4-86cd-b7c51decce87.png'],
            ['name' => 'Personalised Stationery & Pencil Tin', 'image' => '654479a4-4a59-4630-9d59-6c469a42e78e.png'],
            ['name' => 'Custom A3 Wall Print', 'image' => null],
            ['name' => 'Personalised School Backpack', 'image' => null],
            ['name' => 'Personalised Pet Bowl', 'image' => null],
        ];

        $products = Product::query()->active()
            ->whereIn('name', array_column($config, 'name'))
            ->with('images')->get()->keyBy('name');

        return array_map(function (array $tile) use ($products) {
            $product = $products->get($tile['name']);
            $image = null;
            if ($tile['image'] !== null && $product) {
                $image = $product->images
                    ->first(fn ($img) => str_ends_with($img->storage_key, $tile['image']))?->url();
            }

            return [
                'name' => $tile['name'],
                'url' => $product ? route('products.show', $product->slug) : null,
                'image' => $image,
            ];
        }, $config);
    }
}
