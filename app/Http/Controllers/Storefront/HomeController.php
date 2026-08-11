<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ArtworkStyle;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('storefront.home', [
            'featuredProducts' => Product::query()->active()->ordered()->with(['images', 'variants' => fn ($query) => $query->active()->ordered()])->limit(4)->get(),
            'artworkStyles' => ArtworkStyle::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
