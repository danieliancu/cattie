<?php

namespace App\Support;

/**
 * First URL segments that may never be used as a top-level category slug.
 *
 * Top-level categories own the first path segment (`/school-everyday`), so any
 * slug that collides with a fixed application route or a publicly served
 * directory has to be rejected before it is ever saved.
 *
 * This is deliberately a compile-time constant rather than something derived
 * from `Route::getRoutes()`: the route collection cannot be introspected while
 * routes are still being defined, the Livewire endpoint is a hashed path that
 * varies between installations, and `public/` directories are not routes at
 * all. `Tests\Feature\Catalogue\ReservedSlugsTest` asserts this list stays in
 * sync with the real routing table and filesystem.
 */
final class ReservedSlugs
{
    public const SLUGS = [
        // Storefront routes.
        'account', 'address-autocomplete', 'address-lookup', 'admin-preview', 'artwork', 'auth',
        'cart', 'checkout', 'collections', 'delivery-shipping', 'faq', 'login', 'logout',
        'manage-cookies', 'order-support', 'orders', 'payment-methods', 'privacy-policy',
        'products', 'register', 'returns-policy', 'sitemap', 'terms-and-conditions',
        // Framework, admin panel and health endpoints.
        'admin', 'api', 'filament', 'livewire', 'up',
        // Publicly served directories and assets.
        'build', 'css', 'fonts', 'images', 'js', 'storage', 'vendor',
        // Held back for future use.
        'blog', 'search', 'gift-cards',
    ];

    /**
     * Reserved segments whose exact spelling varies between installations, so
     * they cannot be listed literally. Livewire serves its assets and update
     * endpoint from a hashed prefix such as `livewire-a36122cd`.
     *
     * Each entry is a regular expression fragment matching a whole segment.
     */
    public const SLUG_PATTERNS = [
        'livewire-[a-z0-9]+',
    ];

    /** A single lowercase kebab-case URL segment. */
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public static function has(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        if (in_array($slug, self::SLUGS, true)) {
            return true;
        }

        foreach (self::SLUG_PATTERNS as $pattern) {
            if (preg_match('/^'.$pattern.'$/', $slug) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Route requirement for a first path segment: a normal slug that is not reserved.
     *
     * Deliberately unanchored — Symfony's RouteCompiler rejects requirements that
     * start with `^` or end with `$`. The lookahead closes on `(?:/|$)` so that it
     * matches a whole segment: plain `$` would fail to exclude `/admin/login` on
     * the two-segment route.
     */
    public static function routePattern(): string
    {
        $reserved = implode('|', [
            ...array_map('preg_quote', self::SLUGS),
            ...self::SLUG_PATTERNS,
        ]);

        return '(?!(?:'.$reserved.')(?:/|$))'.self::SLUG_PATTERN;
    }
}
