<?php

namespace App\Console\Commands;

use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Domain\Artwork\Actions\StoreArtworkUpload;
use App\Enums\GenerationStatus;
use App\Jobs\GenerateArtwork;
use App\Jobs\NormaliseArtworkUpload;
use App\Models\Product;
use Illuminate\Console\Command;

class OpenAiArtworkSmoke extends Command
{
    protected $signature = 'artwork:openai-smoke
        {photo : Path to a local JPEG, PNG, or WebP photo}
        {--product=water-bottle-with-red-flip-lid : Active product slug}
        {--style=storybook-cartoon : Artwork style slug attached to the product}
        {--name=Test : Personalised name for products that require it}';

    protected $description = 'Run one explicit real OpenAI artwork generation through the existing Cattie pipeline';

    public function handle(StartArtworkSession $start, StoreArtworkUpload $store): int
    {
        if (config('artwork.provider') !== 'openai') {
            $this->error('Set AI_IMAGE_PROVIDER=openai before running this command.');

            return self::FAILURE;
        }
        if (trim((string) config('artwork.openai.api_key')) === '') {
            $this->error('OPENAI_API_KEY is not configured.');

            return self::FAILURE;
        }

        $path = realpath((string) $this->argument('photo'));
        if (! $path || ! is_file($path)) {
            $this->error('The photo path does not exist.');

            return self::FAILURE;
        }
        $bytes = file_get_contents($path);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->error('The photo must be a valid JPEG, PNG, or WebP image.');

            return self::FAILURE;
        }
        if (strlen($bytes) > config('artwork.upload.max_kb') * 1024 || min($info[0], $info[1]) < config('artwork.upload.min_dimension') || max($info[0], $info[1]) > config('artwork.upload.max_dimension')) {
            $this->error('The photo does not meet the configured upload size or dimension limits.');

            return self::FAILURE;
        }

        $product = Product::query()->active()->where('slug', $this->option('product'))->with(['variants', 'artworkStyles', 'personalisationFields'])->first();
        $variant = $product?->variants->firstWhere('is_active', true);
        $style = $product?->artworkStyles->firstWhere('slug', $this->option('style'));
        if (! $product || ! $variant || ! $style) {
            $this->error('The requested active product, variant, or artwork style is unavailable.');

            return self::FAILURE;
        }

        try {
            [$session] = $start->handle($product, [
                'variant_id' => $variant->id,
                'artwork_style_id' => $style->id,
                'personalisation' => ['name' => (string) $this->option('name')],
            ]);
            $upload = $store->handle($session, $bytes, $info['mime'], basename($path), false);
            app()->call([new NormaliseArtworkUpload($upload, false), 'handle']);
            $generation = $session->fresh()->currentGeneration;
            app()->call([new GenerateArtwork($generation), 'handle']);
            $generation->refresh()->load('assets');
        } catch (\Throwable $e) {
            report($e);
            $this->error('The smoke test failed safely. Check the application log using the generated session context.');

            return self::FAILURE;
        }

        $this->table(['Generation', 'Status', 'Model', 'Request ID', 'Cost basis', 'Assets'], [[
            $generation->id,
            $generation->status->value,
            $generation->model,
            $generation->provider_request_id ?: 'unavailable',
            $generation->cost_basis ?: 'unavailable',
            $generation->assets->pluck('kind')->implode(', '),
        ]]);

        if ($generation->status !== GenerationStatus::Succeeded) {
            $this->error('Generation failed: '.($generation->failure_category ?: 'unknown').' / '.($generation->provider_error_code ?: 'no provider code'));

            return self::FAILURE;
        }

        $this->info('Real artwork was generated, stored privately, and passed through the existing product composer.');

        return self::SUCCESS;
    }
}
