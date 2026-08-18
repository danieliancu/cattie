<?php

namespace App\Enums;

enum ArtworkProcessingStage: string
{
    case PreparingPhoto = 'preparing_photo';
    case CreatingIllustration = 'creating_illustration';
    case RemovingBackground = 'removing_background';
    case PreparingPreview = 'preparing_preview';
    case Ready = 'ready';

    public function label(bool $keepsBackground = false): string
    {
        return match ($this) {
            self::PreparingPhoto => 'Preparing your photo…',
            self::CreatingIllustration => 'Creating your illustration…',
            // Products that keep their illustration's background (wall prints — see
            // Product::keepsPhotoBackground) never have it removed, so the mid-stage label
            // must not claim otherwise; the character is being scaled up for print here.
            self::RemovingBackground => $keepsBackground ? 'Composing your scene…' : 'Removing the background…',
            self::PreparingPreview => 'Preparing your preview…',
            self::Ready => 'Your artwork is ready',
        };
    }

    public function progress(): int
    {
        return match ($this) {
            self::PreparingPhoto => 10,
            self::CreatingIllustration => 30,
            self::RemovingBackground => 55,
            self::PreparingPreview => 80,
            self::Ready => 100,
        };
    }
}
