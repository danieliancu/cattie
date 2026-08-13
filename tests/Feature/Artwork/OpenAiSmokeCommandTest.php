<?php

namespace Tests\Feature\Artwork;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenAiSmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_explicit_openai_provider(): void
    {
        config(['artwork.provider' => 'fake']);

        $this->artisan('artwork:openai-smoke', ['photo' => 'missing.jpg'])
            ->expectsOutput('Set AI_IMAGE_PROVIDER=openai before running this command.')
            ->assertExitCode(1);
    }

    public function test_command_requires_api_key_before_reading_photo(): void
    {
        config(['artwork.provider' => 'openai', 'artwork.openai.api_key' => null]);

        $this->artisan('artwork:openai-smoke', ['photo' => 'missing.jpg'])
            ->expectsOutput('OPENAI_API_KEY is not configured.')
            ->assertExitCode(1);
    }
}
