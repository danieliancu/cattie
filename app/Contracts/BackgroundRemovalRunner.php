<?php

namespace App\Contracts;

interface BackgroundRemovalRunner
{
    public function run(string $inputPath, string $outputPath): void;
}
