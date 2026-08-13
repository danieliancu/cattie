<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Jobs\GenerateArtwork;
use App\Models\ArtworkSession;
use App\Services\GenerationPromptBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestArtworkGeneration
{
    public function __construct(private GenerationPromptBuilder $prompts, private RecordAnalyticsEvent $analytics) {}

    public function handle(ArtworkSession $session, bool $regeneration = false, bool $dispatch = true)
    {
        return DB::transaction(function () use ($session, $regeneration, $dispatch) {
            $session = ArtworkSession::query()->lockForUpdate()->findOrFail($session->id);
            $uploadGenerationCount = $session->generations()->where('upload_id', $session->current_upload_id)->count();
            $prompt = $this->prompts->build($session->load('artworkStyle'));
            $sequence = $uploadGenerationCount + 1;
            $parent = $regeneration ? $session->current_generation_id : null;
            $generation = $session->generations()->create(['upload_id' => $session->current_upload_id, 'product_id' => $session->product_id, 'product_variant_id' => $session->product_variant_id, 'artwork_style_id' => $session->artwork_style_id, 'parent_generation_id' => $parent, 'prompt_key' => $prompt['key'], 'prompt_version' => $prompt['version'], 'resolved_prompt' => $prompt['prompt'], 'provider' => config('artwork.provider'), 'model' => config('artwork.model'), 'quality' => config('artwork.quality'), 'output_size' => config('artwork.size'), 'candidate_count' => config('artwork.candidates'), 'idempotency_key' => (string) Str::uuid(), 'status' => GenerationStatus::Queued, 'parameters' => ['generation_sequence' => $sequence, 'output_requirements' => $prompt['output_requirements'], 'input_references' => $prompt['input_references']], 'cost_currency' => 'USD']);
            $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::Generating, 'expires_at' => now()->addDays(config('artwork.retention_days'))]);
            $this->analytics->handle($regeneration ? 'regeneration_requested' : 'generation_requested', $generation);
            if ($dispatch) {
                GenerateArtwork::dispatch($generation)->afterCommit();
            }

            return $generation;
        });
    }
}
