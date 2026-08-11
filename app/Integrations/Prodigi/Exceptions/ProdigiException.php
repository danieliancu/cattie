<?php

namespace App\Integrations\Prodigi\Exceptions;

use RuntimeException;

class ProdigiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $category, public readonly ?int $status = null, public readonly ?string $providerCode = null)
    {
        parent::__construct($message);
    }
}
