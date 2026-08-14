<?php

namespace Database\Seeders;

use App\Models\ArtworkStyle;
use Illuminate\Database\Seeder;

class ArtworkStyleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['name' => 'Storybook Cartoon', 'slug' => 'storybook-cartoon', 'description' => 'A premium, colourful storybook illustration.', 'prompt_key' => 'storybook_cartoon'], ['name' => 'Hand Drawn', 'slug' => 'hand-drawn', 'description' => 'A warm pencil and watercolour-style portrait.', 'prompt_key' => 'hand_drawn']] as $style) {
            ArtworkStyle::query()->updateOrCreate(['slug' => $style['slug']], $style + ['prompt_version' => 5, 'configuration' => [], 'is_active' => true]);
        }
    }
}
