<?php

namespace App\Domain\Artwork\Actions;

use App\Models\ArtworkSession;
use App\Models\DesignTemplateVersion;
use App\Models\GenerationAsset;

final class ResolveDesignManifest
{
    public function __construct(private ResolveDesignTemplateVersion $versions) {}

    /** @return array{template_id:string,template_version_id:?string,template_version:int,variant_id:string,configuration:array} */
    public function handle(ArtworkSession $session): array
    {
        $session->loadMissing(['product.designTemplate', 'variant']);
        $version = $this->versions->handle($session->product, $session->variant);
        $template = $version?->template ?? $session->product->designTemplate;
        $configuration = $version?->configuration ?? $template->definition();
        $configuration = $this->normalise($configuration);

        return [
            'template_id' => $template->id,
            'template_version_id' => $version?->id,
            'template_version' => $version?->version ?? $template->version,
            'variant_id' => $session->variant->id,
            'configuration' => $configuration,
        ];
    }

    public function fingerprint(array $manifest, ArtworkSession $session, GenerationAsset $asset, array $adjustments): string
    {
        $payload = [
            'manifest' => $manifest,
            'asset_id' => $asset->id,
            'personalisation' => $session->personalisation_snapshot,
            'adjustments' => $adjustments,
        ];
        $this->sortRecursive($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Legacy published templates are made safe while their stored configurations are backfilled. */
    public function normalise(array $configuration): array
    {
        $configuration['character_clip'] ??= ['mode' => 'canvas'];
        $printLayers = [];
        foreach ($configuration['layers'] ?? [] as $layer) {
            if (($layer['type'] ?? null) === 'solid') {
                $configuration['preview_surface'] ??= array_filter([
                    'colour' => $layer['colour'] ?? null,
                    'variant_option' => $layer['variant_option'] ?? null,
                    'colours_by_variant' => $layer['colours_by_variant'] ?? null,
                    'fallback_colour' => $layer['fallback_colour'] ?? null,
                ], fn ($value) => $value !== null);
                continue;
            }
            $printLayers[] = $layer;
        }
        $configuration['layers'] = $printLayers;

        return $configuration;
    }

    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
    }
}
