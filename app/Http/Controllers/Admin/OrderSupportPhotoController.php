<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderSupportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderSupportPhotoController extends Controller
{
    public function show(OrderSupportRequest $orderSupportRequest, Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless(
            $orderSupportRequest->photo_storage_key && Storage::disk($orderSupportRequest->photo_disk)->exists($orderSupportRequest->photo_storage_key),
            404
        );

        return Storage::disk($orderSupportRequest->photo_disk)->response($orderSupportRequest->photo_storage_key, null, [
            'Content-Type' => $orderSupportRequest->photo_mime_type,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
