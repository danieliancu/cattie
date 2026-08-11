<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Artwork\Actions\RequestArtworkGeneration;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Enums\ArtworkSessionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\NormaliseArtworkUpload;
use App\Models\ArtworkSession;
use App\Models\GenerationAsset;
use App\Models\Product;
use App\Support\GuestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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

    public function start(Product $product, Request $request, StartArtworkSession $start): RedirectResponse
    {
        abort_unless($product->is_active, 404);
        [$session,$token] = $start->handle($product, $request->all(), $request->cookie('cattie_guest_token'));

        return redirect()->route('artwork.show', $session->public_id)->withCookie(app(GuestContext::class)->cookie($token));
    }

    public function show(string $publicId, Request $request): View
    {
        $session = $this->owned($publicId, $request)->load(['product', 'variant', 'artworkStyle', 'generations.assets', 'approvedAsset']);

        return view('storefront.artwork.show', compact('session'));
    }

    public function upload(string $publicId, Request $request, RecordAnalyticsEvent $analytics): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        if (! in_array($session->status, [ArtworkSessionStatus::AwaitingUpload, ArtworkSessionStatus::Failed], true)) {
            return redirect()->route('artwork.show', $session->public_id);
        }
        $validated = $request->validate(['photo' => ['required', 'file', 'max:'.config('artwork.upload.max_kb'), 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:min_width='.config('artwork.upload.min_dimension').',min_height='.config('artwork.upload.min_dimension').',max_width='.config('artwork.upload.max_dimension').',max_height='.config('artwork.upload.max_dimension')]]);
        $file = $validated['photo'];
        $bytes = file_get_contents($file->getRealPath());
        abort_unless(@getimagesizefromstring($bytes) !== false, 422);
        $key = 'artwork/originals/'.bin2hex(random_bytes(24)).'.'.$file->guessExtension();
        Storage::disk('local')->put($key, $bytes);
        [$width,$height] = getimagesizefromstring($bytes);
        $upload = $session->uploads()->create(['guest_token' => null, 'disk' => 'local', 'storage_key' => $key, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => strlen($bytes), 'width' => $width, 'height' => $height, 'sha256' => hash('sha256', $bytes), 'expires_at' => $session->expires_at]);
        $session->update(['current_upload_id' => $upload->id, 'status' => ArtworkSessionStatus::PreparingPhoto]);
        $analytics->handle('photo_uploaded', $upload);
        NormaliseArtworkUpload::dispatch($upload);

        return redirect()->route('artwork.show', $session->public_id);
    }

    public function status(string $publicId, Request $request): JsonResponse
    {
        $session = $this->owned($publicId, $request)->load('currentGeneration.assets');
        $preview = $session->currentGeneration?->assets->firstWhere('kind', 'web_preview');

        return response()->json(['status' => $session->status->value, 'message' => match ($session->status) {
            ArtworkSessionStatus::PreparingPhoto => 'Preparing your photo…',ArtworkSessionStatus::Generating => 'Creating your illustration…',ArtworkSessionStatus::PreviewReady => 'Your artwork is ready',ArtworkSessionStatus::Failed => 'We couldn’t create your artwork this time.',ArtworkSessionStatus::Approved => 'Artwork approved',ArtworkSessionStatus::Expired => 'This artwork session has expired.',default => 'Upload your photo'
        }, 'preview_url' => $preview ? route('artwork.assets', [$session->public_id, $preview->id]) : null, 'poll_interval_ms' => config('artwork.poll_interval_ms')]);
    }

    public function asset(string $publicId, GenerationAsset $asset, Request $request)
    {
        $session = $this->owned($publicId, $request);
        abort_unless($asset->generation?->artwork_session_id === $session->id, 404);

        return Storage::disk($asset->disk)->response($asset->storage_key, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function regenerate(string $publicId, Request $request, RequestArtworkGeneration $action): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        abort_unless(in_array($session->status, [ArtworkSessionStatus::PreviewReady, ArtworkSessionStatus::Failed], true), 409);
        $action->handle($session, true);

        return back();
    }

    public function approve(string $publicId, Request $request, ApproveArtwork $action): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        $asset = GenerationAsset::query()->findOrFail($request->validate(['asset_id' => 'required|string'])['asset_id']);
        $action->handle($session, $asset);

        return back();
    }

    public function change(string $publicId, Request $request): RedirectResponse
    {
        $session = $this->owned($publicId, $request);
        abort_unless($session->status === ArtworkSessionStatus::Approved, 409);
        $session->approvedAsset?->update(['is_selected' => false, 'selected_at' => null]);
        $session->update(['approved_generation_asset_id' => null, 'approved_at' => null, 'status' => ArtworkSessionStatus::PreviewReady]);

        return back();
    }
}
