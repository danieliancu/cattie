<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class InformationPageController extends Controller
{
    public function __invoke(string $page): View
    {
        $content = config("information-pages.{$page}");

        abort_unless(is_array($content), 404);

        return view('storefront.information.show', compact('content'));
    }
}
