<?php

namespace App\Console\Commands;

use App\Enums\ArtworkSessionStatus;
use App\Models\ArtworkSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredArtworkSessions extends Command
{
    protected $signature = 'artwork:purge-expired';

    protected $description = 'Delete expired unapproved artwork sessions and private assets';

    public function handle(): int
    {
        ArtworkSession::query()->where('expires_at', '<', now())->where('status', '!=', ArtworkSessionStatus::Approved->value)->whereDoesntHave('cartItems')->whereDoesntHave('orderItems')->with(['uploads.assets', 'generations.assets'])->chunkById(100, function ($sessions) {
            foreach ($sessions as $session) {
                foreach ($session->uploads as $upload) {
                    Storage::disk($upload->disk)->delete($upload->storage_key);
                    foreach ($upload->assets as $asset) {
                        Storage::disk($asset->disk)->delete($asset->storage_key);
                    }
                }foreach ($session->generations as $generation) {
                    foreach ($generation->assets as $asset) {
                        Storage::disk($asset->disk)->delete($asset->storage_key);
                    }
                }$session->delete();
            }
        }, 'id');

        return self::SUCCESS;
    }
}
