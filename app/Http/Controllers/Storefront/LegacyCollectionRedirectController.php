<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;

/**
 * /collections/{slug} is no longer canonical. It survives only to redirect the
 * URLs that are already indexed and linked.
 */
class LegacyCollectionRedirectController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        $target = config('catalogue.legacy_collection_slugs.'.$slug, $slug);

        $category = ProductCategory::query()
            ->where('slug', $target)
            ->with('parent:id,slug')
            ->first();

        abort_if($category === null, 404);

        // Asking the category for its own URL keeps this correct at either level
        // of the hierarchy, and never produces a redirect chain.
        return redirect()->to($category->url(), 301);
    }
}
