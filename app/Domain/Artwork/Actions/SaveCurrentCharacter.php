<?php

namespace App\Domain\Artwork\Actions;

use App\Models\ArtworkSession;
use App\Models\GenerationAsset;
use App\Models\SavedCharacter;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SaveCurrentCharacter
{
    public function __construct(private RecordAnalyticsEvent $analytics) {}

    public function handle(User $user, ArtworkSession $session, ?GenerationAsset $asset = null): SavedCharacter
    {
        $session->loadMissing(['currentGeneration.assets', 'artworkStyle']);
        $asset ??= $session->currentGeneration?->assets->firstWhere('kind', 'composition_source');
        if (! $asset || $asset->kind !== 'composition_source' || $asset->generation?->artwork_session_id !== $session->id) {
            throw new RuntimeException('That character cannot be saved.');
        }

        $bytes = Storage::disk($asset->disk)->get($asset->storage_key);
        if (! is_string($bytes) || $bytes === '' || $asset->mime_type !== 'image/png') {
            throw new RuntimeException('The transparent character source is unavailable.');
        }
        if (! $asset->width || ! $asset->height) {
            throw new RuntimeException('The character source is missing its dimensions.');
        }
        $sha256 = hash('sha256', $bytes);
        $existing = $user->savedCharacters()->where('artwork_style_id', $session->artwork_style_id)->where('sha256', $sha256)->first();
        if ($existing) {
            return $existing;
        }

        $preview = $asset->generation->assets->firstWhere('kind', 'web_preview');
        if (! $preview || ! Storage::disk($preview->disk)->exists($preview->storage_key)) {
            throw new RuntimeException('The character preview is unavailable.');
        }
        $previewBytes = Storage::disk($preview->disk)->get($preview->storage_key);
        $character = new SavedCharacter([
            'user_id' => $user->id,
            'artwork_style_id' => $session->artwork_style_id,
            'source_generation_asset_id' => $asset->id,
            'name' => $this->name($user, $session),
            'disk' => 'local',
            'mime_type' => 'image/png',
            'width' => $asset->width,
            'height' => $asset->height,
            'sha256' => $sha256,
        ]);
        $character->id = $character->newUniqueId();
        $directory = "saved-characters/{$user->id}/{$character->id}";
        $character->storage_key = "{$directory}/character.png";
        $character->preview_storage_key = "{$directory}/preview.webp";

        if (! Storage::disk('local')->put($character->storage_key, $bytes)
            || ! Storage::disk('local')->put($character->preview_storage_key, $previewBytes)) {
            Storage::disk('local')->deleteDirectory($directory);
            throw new RuntimeException('The character could not be stored.');
        }

        try {
            $character->save();
        } catch (QueryException $e) {
            Storage::disk('local')->deleteDirectory($directory);
            $existing = $user->savedCharacters()->where('artwork_style_id', $session->artwork_style_id)->where('sha256', $sha256)->first();
            if (! $existing) {
                throw $e;
            }

            return $existing;
        }

        $this->analytics->handle('character_saved', $character, ['artwork_session_id' => $session->id]);

        return $character;
    }

    private function name(User $user, ArtworkSession $session): string
    {
        $name = trim((string) collect($session->personalisation_snapshot)->firstWhere('key', 'name')['value'] ?? '');
        if ($name !== '') {
            return mb_substr($name, 0, 100);
        }
        $existing = $user->savedCharacters()->pluck('name')->all();
        if (! in_array('Character', $existing, true)) {
            return 'Character';
        }
        for ($number = 2; ; $number++) {
            if (! in_array("Character {$number}", $existing, true)) {
                return "Character {$number}";
            }
        }
    }
}
