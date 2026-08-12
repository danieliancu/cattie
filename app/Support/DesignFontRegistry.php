<?php

namespace App\Support;

final class DesignFontRegistry
{
    public const FONTS = [
        'Anton' => 'assets/fonts/Anton-Regular.ttf',
        'Bodoni Moda' => 'assets/fonts/BodoniModa_18pt-Regular.ttf',
        'Fredoka' => 'assets/fonts/Fredoka-SemiBold.ttf',
        'Allura' => 'assets/fonts/Allura-Regular.ttf',
    ];

    public function all(): array
    {
        return self::FONTS;
    }

    public function supports(string $family, string $source): bool
    {
        return (self::FONTS[$family] ?? null) === $source;
    }
}
