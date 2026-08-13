<?php

namespace Tests\Feature\Artwork;

use App\Contracts\BackgroundRemovalRunner;
use App\Exceptions\ImageGenerationException;
use App\Services\BackgroundRemovalProcessor;
use App\Services\LocalBackgroundRemovalRunner;
use Mockery;
use Tests\TestCase;

class BackgroundRemovalProcessorTest extends TestCase
{
    public function test_transparent_source_bypasses_local_model(): void
    {
        $runner = Mockery::mock(BackgroundRemovalRunner::class);
        $runner->shouldNotReceive('run');

        $result = (new BackgroundRemovalProcessor($runner))->process($this->transparentPng(), ['transparent_background' => true]);

        $this->assertFalse($result['processed']);
        $this->assertSame('image/png', $result['mime_type']);
    }

    public function test_opaque_source_is_processed_and_temporary_files_are_removed(): void
    {
        $paths = [];
        $runner = Mockery::mock(BackgroundRemovalRunner::class);
        $runner->shouldReceive('run')->once()->andReturnUsing(function (string $input, string $output) use (&$paths): void {
            $paths = [$input, $output];
            $this->assertFileExists($input);
            file_put_contents($output, $this->transparentPng());
        });

        $result = (new BackgroundRemovalProcessor($runner))->process($this->opaquePng(), ['transparent_background' => true]);

        $this->assertTrue($result['processed']);
        $this->assertSame('image/png', $result['mime_type']);
        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_invalid_processor_output_is_rejected_and_cleaned_up(): void
    {
        $outputPath = null;
        $runner = Mockery::mock(BackgroundRemovalRunner::class);
        $runner->shouldReceive('run')->once()->andReturnUsing(function (string $input, string $output) use (&$outputPath): void {
            $outputPath = $output;
            file_put_contents($output, $this->opaquePng());
        });

        try {
            (new BackgroundRemovalProcessor($runner))->process($this->opaquePng(), ['transparent_background' => true]);
            $this->fail('Expected invalid transparent output failure.');
        } catch (ImageGenerationException $e) {
            $this->assertSame('invalid_transparent_output', $e->providerCode);
            $this->assertFalse($e->retryable);
        }
        $this->assertFileDoesNotExist($outputPath);
    }

    public function test_missing_python_runtime_fails_safely(): void
    {
        config(['artwork.background_removal.python' => 'definitely-not-a-real-python-binary']);

        $this->expectException(ImageGenerationException::class);
        (new LocalBackgroundRemovalRunner)->run(__FILE__, sys_get_temp_dir().'/cattie-never-created.png');
    }

    private function opaquePng(): string
    {
        $image = imagecreatetruecolor(50, 50);
        imagefill($image, 0, 0, imagecolorallocate($image, 230, 230, 230));

        return $this->encode($image);
    }

    private function transparentPng(): string
    {
        $image = imagecreatetruecolor(50, 50);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 10, 5, 39, 44, imagecolorallocatealpha($image, 200, 80, 50, 0));

        return $this->encode($image);
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
