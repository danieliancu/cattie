<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\ResolveDesignManifest;
use App\Domain\Artwork\Actions\ValidateDesignTemplateConfiguration;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DesignManifestTest extends TestCase
{
    public function test_legacy_solid_background_becomes_preview_only_and_canvas_clip_is_explicit(): void
    {
        $configuration = app(ResolveDesignManifest::class)->normalise([
            'layers' => [
                ['id' => 'background', 'type' => 'solid', 'colour' => '#ffffff'],
                ['id' => 'character', 'type' => 'generation_asset'],
            ],
        ]);

        $this->assertSame(['mode' => 'canvas'], $configuration['character_clip']);
        $this->assertSame(['colour' => '#ffffff'], $configuration['preview_surface']);
        $this->assertSame(['character'], array_column($configuration['layers'], 'id'));
    }

    public function test_template_validation_rejects_print_backgrounds_and_invalid_custom_clip(): void
    {
        $base = [
            'key' => 'test', 'version' => 1, 'coordinate_system' => 'normalized',
            'output_size' => ['source' => 'variant_print_area'],
            'safe_zones' => [['id' => 'safe']],
            'layers' => [['id' => 'character', 'type' => 'generation_asset']],
        ];

        try {
            app(ValidateDesignTemplateConfiguration::class)->handle([...$base, 'character_clip' => ['mode' => 'custom', 'x' => .8, 'y' => 0, 'width' => .3, 'height' => 1]]);
            $this->fail('An out-of-canvas custom clip was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('character_clip', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(ValidateDesignTemplateConfiguration::class)->handle([...$base, 'character_clip' => ['mode' => 'canvas'], 'layers' => [['id' => 'background', 'type' => 'solid', 'colour' => '#fff']]]);
    }
}
