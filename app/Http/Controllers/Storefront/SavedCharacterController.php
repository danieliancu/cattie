<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Http\Controllers\Controller;
use App\Models\SavedCharacter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class SavedCharacterController extends Controller
{
    public function index(Request $request): View
    {
        $characters = $request->user()->savedCharacters()->with('artworkStyle')->latest()->get();

        return view('storefront.account.characters', compact('characters'));
    }

    public function preview(SavedCharacter $character, Request $request)
    {
        $character = $request->user()->savedCharacters()->findOrFail($character->id);
        abort_unless(Storage::disk($character->disk)->exists($character->preview_storage_key), 404);

        return Storage::disk($character->disk)->response($character->preview_storage_key, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function destroy(SavedCharacter $character, Request $request, RecordAnalyticsEvent $analytics): RedirectResponse
    {
        $character = $request->user()->savedCharacters()->findOrFail($character->id);
        $analytics->handle('saved_character_deleted', $character);
        Storage::disk($character->disk)->delete([$character->storage_key, $character->preview_storage_key]);
        $character->delete();

        return redirect()->route('account.characters.index')->with('status', 'Character deleted.');
    }
}
