<?php

namespace App\Domain\Artwork\Actions;

use App\Support\DesignFontRegistry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ValidateDesignTemplateConfiguration
{
    public function __construct(private DesignFontRegistry $fonts) {}

    public function handle(array $config): array
    {
        $validator = Validator::make($config, [
            'key' => ['required', 'string'], 'version' => ['required', 'integer', 'min:1'],
            'coordinate_system' => ['required', 'in:normalized'],
            'output_size.source' => ['required', 'in:variant_print_area'],
            'character_clip.mode' => ['required', 'in:canvas,custom'],
            'character_clip.x' => ['required_if:character_clip.mode,custom', 'numeric', 'min:0', 'max:1'],
            'character_clip.y' => ['required_if:character_clip.mode,custom', 'numeric', 'min:0', 'max:1'],
            'character_clip.width' => ['required_if:character_clip.mode,custom', 'numeric', 'gt:0', 'max:1'],
            'character_clip.height' => ['required_if:character_clip.mode,custom', 'numeric', 'gt:0', 'max:1'],
            'safe_zones' => ['required', 'array', 'min:1'], 'layers' => ['required', 'array', 'min:1'],
        ]);
        $validator->after(function ($validator) use ($config): void {
            $clip = $config['character_clip'] ?? [];
            if (($clip['mode'] ?? null) === 'custom'
                && (((float) ($clip['x'] ?? 0) + (float) ($clip['width'] ?? 0) > 1)
                    || ((float) ($clip['y'] ?? 0) + (float) ($clip['height'] ?? 0) > 1))) {
                $validator->errors()->add('character_clip', 'The custom character clip must remain inside the canvas.');
            }
        });
        $validator->validate();
        foreach ($config['layers'] as $layer) {
            if (! in_array($layer['type'] ?? null, ['transparent', 'personalisation_text_pattern', 'generation_asset'], true)) {
                throw ValidationException::withMessages(['layers' => 'Unsupported design layer type.']);
            }
            foreach (($layer['styles'] ?? []) as $style) {
                if (! $this->fonts->supports((string) ($style['font_family'] ?? ''), (string) ($style['font_source'] ?? ''))) {
                    throw ValidationException::withMessages(['fonts' => 'The template contains an unapproved font.']);
                }
            }
        }

        return $config;
    }
}
