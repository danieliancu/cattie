<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Jobs\GenerateArtwork;
use App\Models\ArtworkSession;
use App\Services\GenerationPromptBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestArtworkGeneration
{
    public function __construct(private GenerationPromptBuilder $prompts, private RecordAnalyticsEvent $analytics) {}

    public function handle(ArtworkSession $session, bool $regeneration = false)
    {
        return DB::transaction(function () use ($session, $regeneration) {
            $session = ArtworkSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->generations()->count() >= config('artwork.max_generations_per_session')) {
                throw ValidationException::withMessages(['generation' => 'No more versions are available for this artwork.']);
            } $prompt = $this->prompts->build($session->load('artworkStyle'));
            $sequence = $session->generations()->count() + 1;
            $parent = $regeneration ? $session->current_generation_id : null;
            $generation = $session->generations()->create(['upload_id' => $session->current_upload_id, 'product_id' => $session->product_id, 'product_variant_id' => $session->product_variant_id, 'artwork_style_id' => $session->artwork_style_id, 'parent_generation_id' => $parent, 'prompt_key' => $prompt['key'], 'prompt_version' => $prompt['version'], 'resolved_prompt' => $prompt['prompt'], 'provider' => config('artwork.provider'), 'model' => config('artwork.model'), 'quality' => config('artwork.quality'), 'output_size' => config('artwork.size'), 'candidate_count' => config('artwork.candidates'), 'idempotency_key' => (string) Str::uuid(), 'status' => GenerationStatus::Queued, 'parameters' => ['generation_sequence' => $sequence], 'cost_currency' => 'USD']);
            $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::Generating, 'expires_at' => now()->addDays(config('artwork.retention_days'))]);
            $this->analytics->handle($regeneration ? 'regeneration_requested' : 'generation_requested', $generation);
            GenerateArtwork::dispatch($generation)->afterCommit();

            return $generation;
        });
    }
}
