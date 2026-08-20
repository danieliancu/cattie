<?php

namespace App\Contracts;

use App\Services\PhotoModerationResult;

interface PhotoModerator
{
    public function moderate(string $absolutePath, string $mimeType): PhotoModerationResult;
}
