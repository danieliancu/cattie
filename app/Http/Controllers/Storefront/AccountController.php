<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        return view('storefront.account.index', [
            'firstName' => $profile?->first_name,
            'lastName' => $profile?->last_name,
            'hasPassword' => $user->password !== null,
        ]);
    }

    public function orders(Request $request): View
    {
        $orders = $request->user()->orders()->withCount('items')->latest('placed_at')->latest('created_at')->paginate(10);

        return view('storefront.account.orders', compact('orders'));
    }

    public function show(string $orderNumber, Request $request): View
    {
        $order = $this->ownedOrder($orderNumber, $request)->load(['items.composedDesign', 'items.generationAsset']);

        return view('storefront.account.order', compact('order'));
    }

    public function artwork(string $orderNumber, OrderItem $item, Request $request)
    {
        $order = $this->ownedOrder($orderNumber, $request);
        abort_unless($item->order_id === $order->id, 404);
        $item->loadMissing(['composedDesign', 'generationAsset']);
        if ($item->composedDesign?->preview_storage_key) {
            $asset = $item->composedDesign;

            return Storage::disk($asset->disk)->response($asset->preview_storage_key, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=300']);
        }
        $asset = $item->generationAsset;
        abort_unless($asset && Storage::disk($asset->disk)->exists($asset->storage_key), 404);

        return Storage::disk($asset->disk)->response($asset->storage_key, null, ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, max-age=300']);
    }

    private function ownedOrder(string $number, Request $request): Order
    {
        return Order::query()->where('user_id', $request->user()->id)->where('number', $number)->firstOrFail();
    }
}
