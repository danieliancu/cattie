<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class GuestContext
{
    public const COOKIE = 'cattie_guest_token';

    public function token(Request $request): ?string
    {
        return $request->cookie(self::COOKIE);
    }

    public function tokenOrCreate(Request $request): string
    {
        return $this->token($request) ?: Str::random(64);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function owns(?string $hash, Request $request): bool
    {
        $token = $this->token($request);

        return $hash && $token && hash_equals($hash, $this->hash($token));
    }

    public function cookie(string $token)
    {
        return cookie(self::COOKIE, $token, 60 * 24 * 365, null, null, app()->environment('production'), true, false, 'Lax');
    }
}
