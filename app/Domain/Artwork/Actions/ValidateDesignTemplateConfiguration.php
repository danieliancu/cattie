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
        Validator::make($config, [
            'key' => ['required', 'string'], 'version' => ['required', 'integer', 'min:1'],
            'coordinate_system' => ['required', 'in:normalized'],
            'output_size.source' => ['required', 'in:variant_print_area'],
            'safe_zones' => ['required', 'array', 'min:1'], 'layers' => ['required', 'array', 'min:1'],
        ])->validate();
        foreach ($config['layers'] as $layer) {
            if (! in_array($layer['type'] ?? null, ['transparent', 'solid', 'personalisation_text_pattern', 'generation_asset'], true)) {
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
