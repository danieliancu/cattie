<?php

namespace App\Services;

final class PhotoModerationResult
{
    public const UNSAFE = 'unsafe';

    public const NO_SUBJECT = 'no_subject';

    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
    ) {}

    public static function allowed(): self
    {
        return new self(true, null);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
