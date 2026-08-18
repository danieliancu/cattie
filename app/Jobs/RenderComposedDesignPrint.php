<?php

namespace App\Jobs;

use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Models\ComposedDesign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders the full-resolution print PNG for an approved composed design off the web request.
 * The full wall-print canvas (A3 ≈ 18.7 MP, A2 ≈ 36.7 MP) plus its PNG encode buffer would
 * blow a web process's memory_limit, so the heavy render is deferred here. The approve flow
 * redirects immediately; the print file is attached to the design row when this job runs.
 */
class RenderComposedDesignPrint implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public $timeout = 240;

    public function __construct(public ComposedDesign $design) {}

    public function uniqueId(): string
    {
        return $this->design->id;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->design->id))->expireAfter(300)];
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(RenderComposedDesign $render): void
    {
        $design = ComposedDesign::query()->find($this->design->id);
        if (! $design || $design->storage_key) {
            return;
        }
        $render->renderPrintFile($design);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Full-resolution print render failed for composed design.', [
            'composed_design_id' => $this->design->id,
            'exception' => $e->getMessage(),
        ]);
    }
}
