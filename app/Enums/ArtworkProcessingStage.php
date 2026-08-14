<?php

namespace App\Enums;

enum ArtworkProcessingStage: string
{
    case PreparingPhoto = 'preparing_photo';
    case CreatingIllustration = 'creating_illustration';
    case RemovingBackground = 'removing_background';
    case PreparingPreview = 'preparing_preview';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::PreparingPhoto => 'Preparing your photo…',
            self::CreatingIllustration => 'Creating your illustration…',
            self::RemovingBackground => 'Removing the background…',
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
