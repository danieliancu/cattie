<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\Paginator;

class CanonicalUrl
{
    public static function forPaginator(string $url, Paginator $paginator): string
    {
        return $paginator->currentPage() > 1 ? $url.'?page='.$paginator->currentPage() : $url;
    }
}
