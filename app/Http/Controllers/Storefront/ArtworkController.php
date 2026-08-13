<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Artwork\Actions\RequestArtworkGeneration;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Domain\Artwork\Actions\StoreArtworkUpload;
use App\Enums\ArtworkSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ArtworkSession;
use App\Models\ComposedDesign;
use App\Models\GenerationAsset;
use App\Models\Product;
use App\Support\GuestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArtworkController extends Controller
{
    private function owned(string $publicId, Request $request): ArtworkSession
    {
        $session = ArtworkSession::query()->where('public_id', $publicId)->first();
        abort_unless($session && app(GuestContext::class)->owns($session->access_token_hash, $request), 404);
        if ($session->expires_at->isPast() && $session->status !== ArtworkSessionStatus::Approved) {
            $session->update(['status' => ArtworkSessionStatus::Expired]);
        }

        return $session;
    }

    public function start(Product $product, Request $request, StartArtworkSession $start, StoreArtworkUpload $store): RedirectResponse
    {
        abort_unless($product->is_active, 404);
        $file = $this->validatedPhoto($request);
        [$session,$token] = $start->handle($product, $request->all(), $request->cookie('cattie_guest_token'));
        $store->handle($session, file_get_contents($file->getRealPath()), $file->getMimeType(), $file->getClientOriginalName());

        return redirect()->route('products.show', $product->slug)->withCookie(app(GuestContext::class)->cookie($token));
    }

    public function show(string $publicId, Request $request): RedirectResponse
    {
        $session = $this->owned($publicId, $request)->load('product');

        return redirect()->route('products.show', $session->product->slug);
    }

    public function upload(string $publicId, Request $request, StoreArtworkUpload $store, StartArtworkSession $configuration, RenderComposedDesign $render): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        if (! in_array($session->status, [ArtworkSessionStatus::AwaitingUpload, ArtworkSessionStatus::Failed], true)) {
            return redirect()->route('products.show', $session->product->slug);
        }
        if ($request->filled('variant_id')) {
            [$variant, $style, $snapshot] = $configuration->validatedConfiguration($session->product->load(['variants', 'artworkStyles', 'personalisationFields']), $request->all());
            $session->update([
                'product_variant_id' => $variant->id,
                'artwork_style_id' => $style->id,
                'personalisation_snapshot' => $snapshot,
            ]);
        }
        if (! $request->hasFile('photo') && $session->currentGeneration) {
            $session->load(['product.designTemplate', 'currentGeneration.assets']);
            if ($session->product->designTemplate) {
                $asset = $session->currentGeneration->assets->firstWhere('kind', 'composition_source')
                    ?? $session->currentGeneration->assets->firstWhere('kind', 'provider_original');
                abort_unless($asset, 422);
                $currentDesign = $session->composedDesigns()
                    ->where('generation_asset_id', $asset->id)
                    ->where('product_variant_id', $session->product_variant_id)
                    ->latest('created_at')
                    ->latest('id')
                    ->first();
                $render->handle($session->fresh(), $asset, $currentDesign?->character_adjustments ?? []);
            }
            $session->update(['status' => ArtworkSessionStatus::PreviewReady]);

            return redirect()->route('products.show', $session->product->slug);
        }
        $file = $this->validatedPhoto($request);
        $store->handle($session, file_get_contents($file->getRealPath()), $file->getMimeType(), $file->getClientOriginalName());

        return redirect()->route('products.show', $session->product->slug);
    }

    private function validatedPhoto(Request $request)
    {
        return $request->validate(['photo' => ['required', 'file', 'max:'.config('artwork.upload.max_kb'), 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:min_width='.config('artwork.upload.min_dimension').',min_height='.config('artwork.upload.min_dimension').',max_width='.config('artwork.upload.max_dimension').',max_height='.config('artwork.upload.max_dimension')]])['photo'];
    }

    public function status(string $publicId, Request $request): JsonResponse
    {
        $session = $this->owned($publicId, $request)->load('currentGeneration.assets');
        $preview = $session->currentGeneration?->assets->firstWhere('kind', 'web_preview');

        return response()->json(['status' => $session->status->value, 'message' => match ($session->status) {
            ArtworkSessionStatus::PreparingPhoto => 'Preparing your photo…',ArtworkSessionStatus::Generating => 'Creating your illustration…',ArtworkSessionStatus::PreviewReady => 'Your artwork is ready',ArtworkSessionStatus::Failed => 'We couldn’t create your artwork this time.',ArtworkSessionStatus::Approved => 'Artwork approved',ArtworkSessionStatus::Expired => 'This artwork session has expired.',default => 'Upload your photo'
        }, 'preview_url' => $preview ? route('artwork.assets', [$session->public_id, $preview->id]) : null, 'poll_interval_ms' => config('artwork.poll_interval_ms')]);
    }

    public function asset(string $publicId, GenerationAsset $asset, Request $request, RenderComposedDesign $render)
    {
        $session = $this->owned($publicId, $request);
        abort_unless($asset->generation?->artwork_session_id === $session->id, 404);

        if ($request->boolean('trim')) {
            return response($render->trimTransparentImage(Storage::disk($asset->disk)->get($asset->storage_key)), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        return Storage::disk($asset->disk)->response($asset->storage_key, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function design(string $publicId, ComposedDesign $design, Request $request)
    {
        $session = $this->owned($publicId, $request);
        abort_unless($design->artwork_session_id === $session->id, 404);

        return Storage::disk($design->disk)->response($design->preview_storage_key, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=300']);
    }

    public function designEditorBackground(string $publicId, ComposedDesign $design, Request $request)
    {
        $session = $this->owned($publicId, $request);
        abort_unless($design->artwork_session_id === $session->id && $design->editor_background_storage_key, 404);

        return Storage::disk($design->disk)->response($design->editor_background_storage_key, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=300']);
    }

    public function designLayout(string $publicId, ComposedDesign $design, Request $request, RenderComposedDesign $render): JsonResponse
    {
        $session = $this->owned($publicId, $request);
        abort_unless($design->artwork_session_id === $session->id, 404);
        abort_unless($session->status === ArtworkSessionStatus::PreviewReady, 409);
        $adjustments = $request->validate([
            'scale' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'offset_x' => ['required', 'numeric', 'min:-1000', 'max:1000'],
            'offset_y' => ['required', 'numeric', 'min:-1000', 'max:1000'],
        ]);
        $updated = $render->handle($session, $design->generationAsset, array_map('floatval', $adjustments));

        return response()->json([
            'design_id' => $updated->id,
            'asset_id' => $updated->generation_asset_id,
            'preview_url' => route('artwork.designs', [$session->public_id, $updated]),
            'layout_url' => route('artwork.design-layout', [$session->public_id, $updated]),
            'background_url' => route('artwork.design-editor-background', [$session->public_id, $updated]),
        ]);
    }

    public function regenerate(string $publicId, Request $request, RequestArtworkGeneration $action): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        abort_unless(in_array($session->status, [ArtworkSessionStatus::PreviewReady, ArtworkSessionStatus::Failed], true), 409);
        $action->handle($session, true);

        return redirect()->route('products.show', $session->product->slug);
    }

    public function approve(string $publicId, Request $request, ApproveArtwork $action): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        $validated = $request->validate(['asset_id' => 'required|string', 'design_id' => 'nullable|string']);
        $asset = GenerationAsset::query()->findOrFail($validated['asset_id']);
        $design = isset($validated['design_id']) ? ComposedDesign::query()->findOrFail($validated['design_id']) : null;
        $action->handle($session, $asset, $design);

        return redirect()->route('products.show', $session->product->slug);
    }

    public function variant(string $publicId, Request $request, RenderComposedDesign $render): RedirectResponse|JsonResponse
    {
        $session = $this->owned($publicId, $request)->load(['product.variants', 'currentGeneration.assets']);
        abort_unless($session->status === ArtworkSessionStatus::PreviewReady && $session->product->designTemplate, 409);
        $validated = $request->validate([
            'variant_id' => ['required', 'string'],
            'design_id' => [$request->expectsJson() ? 'required' : 'nullable', 'string'],
        ]);
        $variant = $session->product->variants->firstWhere('id', $validated['variant_id']);
        abort_unless($variant?->is_active, 422);
        $currentDesign = null;
        if (isset($validated['design_id'])) {
            $currentDesign = $session->composedDesigns()->whereKey($validated['design_id'])->firstOrFail();
        }
        $previousVariantId = $session->product_variant_id;
        $session->update(['product_variant_id' => $variant->id]);

        if ($request->expectsJson()) {
            try {
                $design = $render->handle($session->fresh(), $currentDesign->generationAsset, $currentDesign->character_adjustments ?? []);
            } catch (\Throwable $e) {
                $session->update(['product_variant_id' => $previousVariantId]);
                report($e);

                return response()->json(['message' => 'We could not update that colour. Please try again.'], 422);
            }

            return response()->json([
                'variant_id' => $variant->id,
                'surface_colour' => $session->product->preview_configuration['design_surfaces_by_variant'][strtolower($variant->options['colour'] ?? '')] ?? '#f4efe7',
                'design_id' => $design->id,
                'asset_id' => $design->generation_asset_id,
                'preview_url' => route('artwork.designs', [$session->public_id, $design]),
                'layout_url' => route('artwork.design-layout', [$session->public_id, $design]),
                'background_url' => route('artwork.design-editor-background', [$session->public_id, $design]),
            ]);
        }

        return redirect()->route('products.show', $session->product->slug);
    }

    public function name(string $publicId, Request $request, RenderComposedDesign $render): JsonResponse
    {
        $session = $this->owned($publicId, $request)->load(['product.designTemplate', 'currentGeneration.assets']);
        abort_unless($session->status === ArtworkSessionStatus::PreviewReady && $session->product->designTemplate, 409);
        $validated = $request->validate([
            'name' => ['present', 'nullable', 'string', 'max:12'],
            'design_id' => ['required', 'string'],
        ]);
        $currentDesign = $session->composedDesigns()->whereKey($validated['design_id'])->firstOrFail();
        $asset = $session->currentGeneration?->assets->firstWhere('kind', 'composition_source')
            ?? $session->currentGeneration?->assets->firstWhere('kind', 'provider_original');
        abort_unless($asset && $currentDesign->generation_asset_id === $asset->id, 422);
        $previousSnapshot = $session->personalisation_snapshot;
        $snapshot = collect($previousSnapshot)->map(function (array $field) use ($validated) {
            if (($field['key'] ?? null) === 'name') {
                $field['value'] = $validated['name'] ?? '';
            }

            return $field;
        })->all();
        $session->update(['personalisation_snapshot' => $snapshot]);

        try {
            $design = $render->handle($session->fresh(), $asset, $currentDesign->character_adjustments ?? []);
        } catch (\Throwable $e) {
            $session->update(['personalisation_snapshot' => $previousSnapshot]);
            report($e);

            return response()->json(['message' => 'We could not update that name. Please try again.'], 422);
        }

        return response()->json([
            'name' => $validated['name'] ?? '',
            'design_id' => $design->id,
            'preview_url' => route('artwork.designs', [$session->public_id, $design]),
            'layout_url' => route('artwork.design-layout', [$session->public_id, $design]),
            'background_url' => route('artwork.design-editor-background', [$session->public_id, $design]),
        ]);
    }

    public function change(string $publicId, Request $request): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        if ($session->status === ArtworkSessionStatus::AwaitingUpload) {
            return redirect()->route('products.show', $session->product->slug);
        }
        abort_unless(in_array($session->status, [ArtworkSessionStatus::PreviewReady, ArtworkSessionStatus::Failed, ArtworkSessionStatus::Approved], true), 409);
        if ($request->filled('design_id')) {
            $design = ComposedDesign::query()
                ->whereKey($request->validate(['design_id' => ['nullable', 'string']])['design_id'])
                ->where('artwork_session_id', $session->id)
                ->with('generationAsset')
                ->firstOrFail();
            $session->current_generation_id = $design->generationAsset->generation_id;
        }
        $session->approvedAsset?->update(['is_selected' => false, 'selected_at' => null]);
        $session->update([
            'approved_generation_asset_id' => null,
            'approved_composed_design_id' => null,
            'approved_at' => null,
            'status' => ArtworkSessionStatus::AwaitingUpload,
            'expires_at' => now()->addDays(config('artwork.retention_days')),
        ]);

        return redirect()->route('products.show', $session->product->slug);
    }
}
