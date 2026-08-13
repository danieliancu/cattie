<?php

namespace Tests\Feature\Artwork;

use App\Data\ImageGenerationReference;
use App\Data\ImageGenerationRequest;
use App\Exceptions\ImageGenerationException;
use App\Providers\ImageGeneration\OpenAiImageGenerationProvider;
use App\Services\AiGenerationCostCalculator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['artwork.openai.api_key' => 'test-key']);
    }

    public function test_adapter_maps_configured_edit_request_without_real_network(): void
    {
        $path = $this->temporaryImage();
        Http::fake(['*/images/edits' => Http::response(['data' => [['b64_json' => base64_encode($this->png())]], 'usage' => ['input_tokens' => 123, 'output_tokens' => 456]], 200, ['x-request-id' => 'req_123'])]);

        $result = (new OpenAiImageGenerationProvider)->generate(new ImageGenerationRequest('gen', 'identity-first prompt', $path, 'image/png', 'gpt-image-2', 'medium', '1024x1536', 1));

        $this->assertSame('image/png', $result->mimeType);
        $this->assertSame('req_123', $result->requestId);
        $this->assertSame(123, $result->usage['input_tokens']);
        Http::assertSent(function (Request $request) {
            $body = $request->body();

            return $request->url() === config('artwork.openai.base_url').'/images/edits'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && str_contains($body, 'gpt-image-2')
                && str_contains($body, 'identity-first prompt')
                && str_contains($body, 'medium')
                && str_contains($body, '1024x1536')
                && str_contains($body, 'output_format')
                && str_contains($body, 'png')
                && ! str_contains($body, 'input_fidelity')
                && ! str_contains($body, 'transparent');
        });
        unlink($path);
    }

    public function test_missing_api_key_fails_without_network_request(): void
    {
        config(['artwork.openai.api_key' => null]);
        Http::fake();

        try {
            (new OpenAiImageGenerationProvider)->generate($this->request($this->temporaryImage()));
            $this->fail('Expected configuration failure.');
        } catch (ImageGenerationException $e) {
            $this->assertSame('configuration', $e->category);
            $this->assertSame('missing_api_key', $e->providerCode);
            $this->assertFalse($e->retryable);
        }
        Http::assertNothingSent();
    }

    public function test_customer_photo_is_sent_before_the_style_only_reference(): void
    {
        $contentPath = $this->temporaryImage();
        $referencePath = $this->temporaryImage();
        $reference = new ImageGenerationReference('storybook-cartoon-v3', 'style_only', $referencePath, 'image/png', hash_file('sha256', $referencePath));
        Http::fake(['*/images/edits' => Http::response(['data' => [['b64_json' => base64_encode($this->png())]]])]);

        (new OpenAiImageGenerationProvider)->generate(new ImageGenerationRequest('gen', 'IMAGE 1 content; IMAGE 2 style only', $contentPath, 'image/png', 'gpt-image-2', 'medium', '1024x1536', 1, 1, [], [$reference]));

        Http::assertSent(function (Request $request) {
            $body = $request->body();

            return substr_count($body, 'name="image[]"') === 2
                && strpos($body, 'filename="content.png"') < strpos($body, 'filename="reference-1.png"');
        });
        unlink($contentPath);
        unlink($referencePath);
    }

    public function test_invalid_style_reference_is_a_permanent_safe_failure(): void
    {
        $contentPath = $this->temporaryImage();
        $reference = new ImageGenerationReference('storybook-cartoon-v3', 'style_only', $contentPath.'.missing', 'image/png', str_repeat('a', 64));
        Http::fake();

        try {
            (new OpenAiImageGenerationProvider)->generate(new ImageGenerationRequest('gen', 'prompt', $contentPath, 'image/png', 'gpt-image-2', 'medium', '1024x1536', 1, 1, [], [$reference]));
            $this->fail('Expected invalid reference failure.');
        } catch (ImageGenerationException $e) {
            $this->assertSame('input', $e->category);
            $this->assertSame('invalid_reference_image', $e->providerCode);
            $this->assertFalse($e->retryable);
        }
        Http::assertNothingSent();
        unlink($contentPath);
    }

    public function test_malformed_image_response_is_a_permanent_failure(): void
    {
        Http::fake(['*/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('not-an-image')]]])]);

        try {
            (new OpenAiImageGenerationProvider)->generate($this->request($this->temporaryImage()));
            $this->fail('Expected invalid response failure.');
        } catch (ImageGenerationException $e) {
            $this->assertSame('invalid_response', $e->category);
            $this->assertSame('malformed_image', $e->providerCode);
            $this->assertFalse($e->retryable);
        }
    }

    public function test_authentication_failure_is_permanent(): void
    {
        $this->assertFailureForStatus(401, 'authentication', false);
    }

    public function test_rate_limit_is_retryable(): void
    {
        $this->assertFailureForStatus(429, 'rate_limit', true);
    }

    public function test_server_error_is_retryable(): void
    {
        $this->assertFailureForStatus(503, 'provider_unavailable', true);
    }

    public function test_connection_failure_is_retryable(): void
    {
        Http::fake(fn () => Http::failedConnection('timeout'));

        try {
            (new OpenAiImageGenerationProvider)->generate($this->request($this->temporaryImage()));
            $this->fail('Expected connection failure.');
        } catch (ImageGenerationException $e) {
            $this->assertSame('transport', $e->category);
            $this->assertTrue($e->retryable);
        }
    }

    public function test_cost_is_actual_only_when_explicitly_supplied_and_otherwise_unavailable(): void
    {
        $calculator = new AiGenerationCostCalculator;
        $actual = $calculator->calculate('gpt-image-2', 'medium', '1024x1536', ['total_cost_micros' => 65432, 'cost_currency' => 'USD']);
        $unknown = $calculator->calculate('gpt-image-2', 'medium', '1024x1536', ['input_tokens' => 100]);

        $this->assertSame('actual', $actual['basis']);
        $this->assertSame(65432, $actual['micros']);
        $this->assertSame('unavailable', $unknown['basis']);
        $this->assertNull($unknown['micros']);
    }

    private function assertFailureForStatus(int $status, string $category, bool $retryable): void
    {
        Http::fake(['*/images/edits' => Http::response(['error' => ['code' => 'provider_code']], $status)]);

        try {
            (new OpenAiImageGenerationProvider)->generate($this->request($this->temporaryImage()));
            $this->fail('Expected provider failure.');
        } catch (ImageGenerationException $e) {
            $this->assertSame($category, $e->category);
            $this->assertSame('provider_code', $e->providerCode);
            $this->assertSame($retryable, $e->retryable);
        }
    }

    private function request(string $path): ImageGenerationRequest
    {
        return new ImageGenerationRequest('gen', 'prompt', $path, 'image/png', 'gpt-image-2', 'medium', '1024x1536', 1);
    }

    private function temporaryImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cattie');
        file_put_contents($path, $this->png());

        return $path;
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 50));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
