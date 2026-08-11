<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Models\ArtworkSession;
use App\Models\Product;
use App\Support\GuestContext;
use Illuminate\Http\Request;

class ResolveResumableArtworkSession
{
    public function __construct(private GuestContext $guest) {}

    public function handle(Product $product, Request $request): ?ArtworkSession
    {
        $token = $this->guest->token($request);
        if (! $token) {
            return null;
        }

        $resumable = [
            ArtworkSessionStatus::AwaitingUpload->value,
            ArtworkSessionStatus::PreparingPhoto->value,
            ArtworkSessionStatus::Generating->value,
            ArtworkSessionStatus::PreviewReady->value,
            ArtworkSessionStatus::Failed->value,
        ];

        $session = ArtworkSession::query()
            ->whereBelongsTo($product)
            ->where('access_token_hash', $this->guest->hash($token))
            ->where('expires_at', '>', now())
            ->where(function ($query) use ($resumable) {
                $query->whereIn('status', $resumable)
                    ->orWhere(function ($query) {
                        $query->where('status', ArtworkSessionStatus::Approved->value)
                            ->whereDoesntHave('cartItems');
                    });
            })
            ->with(['variant', 'artworkStyle', 'currentUpload', 'generations.assets.composedDesigns', 'composedDesigns', 'approvedAsset', 'approvedComposedDesign'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $session?->setRelation('product', $product);
    }
}
