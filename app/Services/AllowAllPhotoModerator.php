<?php

namespace App\Services;

use App\Contracts\PhotoModerator;

final class AllowAllPhotoModerator implements PhotoModerator
{
    public function moderate(string $absolutePath, string $mimeType): PhotoModerationResult
    {
        return PhotoModerationResult::allowed();
    }
}
