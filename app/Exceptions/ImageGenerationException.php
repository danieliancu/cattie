<?php

namespace App\Exceptions;

use RuntimeException;

class ImageGenerationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $category = 'provider', public readonly ?string $providerCode = null, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
