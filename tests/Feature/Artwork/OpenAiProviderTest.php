<?php

namespace Tests\Feature\Artwork;

use App\Data\ImageGenerationRequest;
use App\Providers\ImageGeneration\OpenAiImageGenerationProvider;
use App\Services\AiGenerationCostCalculator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function test_adapter_maps_configured_edit_request_without_real_network(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cattie');
        file_put_contents($path, 'image');
        Http::fake(['*/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('generated')]], 'usage' => ['input_image_tokens' => 123, 'output_image_tokens' => 456]], 200, ['x-request-id' => 'req_123'])]);
        $result = (new OpenAiImageGenerationProvider)->generate(new ImageGenerationRequest('gen', 'prompt', $path, 'image/webp', 'gpt-image-2', 'medium', '1024x1536', 1));
        $this->assertSame('generated', $result->contents);
        $this->assertSame('req_123', $result->requestId);
        Http::assertSent(function ($request) {
            $body = $request->body();

            return $request->url() === config('artwork.openai.base_url').'/images/edits'
                && str_contains($body, 'gpt-image-2') && str_contains($body, 'medium')
                && str_contains($body, '1024x1536') && str_contains($body, 'image');
        });
        unlink($path);
    }

    public function test_cost_is_actual_when_supplied_and_estimated_otherwise(): void
    {
        $calculator = new AiGenerationCostCalculator;
        $actual = $calculator->calculate('gpt-image-2', 'medium', '1024x1536', ['total_cost_micros' => 65432, 'cost_currency' => 'USD']);
        $estimated = $calculator->calculate('gpt-image-2', 'medium', '1024x1536', []);
        $this->assertSame(['micros' => 65432, 'currency' => 'USD', 'basis' => 'actual', 'pricing_version' => config('ai-pricing.version')], $actual);
        $this->assertSame('estimated', $estimated['basis']);
        $this->assertSame(70000, $estimated['micros']);
    }
}
