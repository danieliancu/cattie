<?php

namespace App\Domain\Artwork\Actions;

use App\Models\ArtworkSession;
use App\Models\GenerationAsset;
use App\Models\User;
use App\Support\GuestContext;
use Illuminate\Http\Request;

final class CompletePendingCharacterSave
{
    public const SESSION_KEY = 'pending_character_save';

    public function __construct(private SaveCurrentCharacter $save, private GuestContext $guest) {}

    public function handle(Request $request, User $user): ?string
    {
        $intent = $request->session()->get(self::SESSION_KEY);
        if (! is_array($intent)) {
            return null;
        }
        $request->session()->forget(self::SESSION_KEY);
        if (! isset($intent['artwork_session_id'], $intent['generation_asset_id'], $intent['ownership_hash'], $intent['expires_at'])
            || now()->timestamp > (int) $intent['expires_at']) {
            return null;
        }
        $token = $this->guest->token($request);
        if (! $token || ! hash_equals((string) $intent['ownership_hash'], $this->guest->hash($token))) {
            return null;
        }
        $session = ArtworkSession::query()->with(['product', 'currentGeneration.assets'])->find($intent['artwork_session_id']);
        $asset = GenerationAsset::query()->find($intent['generation_asset_id']);
        if (! $session || ! $asset || ! hash_equals($session->access_token_hash, (string) $intent['ownership_hash'])
            || $asset->generation?->artwork_session_id !== $session->id || $asset->kind !== 'composition_source'
            || ($session->user_id !== null && $session->user_id !== $user->id)) {
            return null;
        }
        if ($session->user_id === null) {
            $session->update(['user_id' => $user->id]);
        }
        $this->save->handle($user, $session, $asset);

        return route('products.show', $session->product->slug);
    }
}
