<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\ArtworkSession;
use App\Models\GenerationAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveArtwork
{
    public function __construct(private RecordAnalyticsEvent $analytics) {}

    public function handle(ArtworkSession $session, GenerationAsset $asset): void
    {
        DB::transaction(function () use ($session, $asset) {
            $session = ArtworkSession::query()->lockForUpdate()->findOrFail($session->id);
            $asset->load('generation');
            if ($asset->generation->artwork_session_id !== $session->id || $asset->generation->status !== GenerationStatus::Succeeded) {
                throw ValidationException::withMessages(['asset' => 'That artwork cannot be approved.']);
            } $session->generations()->each(fn ($generation) => $generation->assets()->update(['is_selected' => false, 'selected_at' => null]));
            $asset->update(['is_selected' => true, 'selected_at' => now()]);
            $session->update(['approved_generation_asset_id' => $asset->id, 'status' => ArtworkSessionStatus::Approved, 'approved_at' => now()]);
            $this->analytics->handle('artwork_approved', $session);
        });
    }
}
