<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Cart\Actions\CreateOrderFromCart;
use App\Domain\Cart\Actions\RefreshCartPrices;
use App\Domain\Cart\Actions\ResolveGuestCart;
use App\Domain\Payments\Actions\ResolveOrderTotals;
use App\Domain\Payments\Actions\StartPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, ResolveGuestCart $resolve, RefreshCartPrices $prices, RecordAnalyticsEvent $analytics): View|RedirectResponse
    {
        [$cart] = $resolve->handle($request, false);
        if (! $cart || ! $cart->items()->exists()) {
            return redirect()->route('cart.index');
        }
        $prices->handle($cart);
        $cart->load(['items.artworkSession', 'items.generationAsset']);
        $analytics->handle('checkout_started', $cart);
        $checkoutKey = (string) Str::uuid();

        return view('storefront.checkout.show', compact('cart', 'checkoutKey'));
    }

    public function store(CheckoutRequest $request, ResolveGuestCart $resolve, CreateOrderFromCart $create, GuestContext $guest): RedirectResponse
    {
        [$cart] = $resolve->handle($request, false);
        if (! $cart && $guest->token($request)) {
            $cart = Cart::query()
                ->where('access_token_hash', $guest->hash($guest->token($request)))
                ->whereNotNull('converted_order_id')->latest()->first();
        }
        abort_unless($cart, 404);
        $order = $create->handle($cart, $request->validated('pricing_hash'), $request->customer(), $request->validated('checkout_idempotency_key'));

        return redirect()->route('checkout.payment', $order->number);
    }

    public function payment(string $orderNumber, Request $request, GuestContext $guest, ResolveOrderTotals $totals): View|RedirectResponse
    {
        $order = $this->ownedOrder($orderNumber, $request, $guest);
        if ($order->status === OrderStatus::Paid) {
            return redirect()->route('orders.confirmation', $order->number);
        }
        $order = $totals->handle($order)->load(['items.artworkSession', 'items.generationAsset', 'payments']);
        $fakeEnabled = config('payments.provider') === 'fake' && config('payments.fake.enabled') && app()->environment(['local', 'testing']);
        $paymentKey = (string) Str::uuid();

        return view('storefront.checkout.payment', compact('order', 'fakeEnabled', 'paymentKey'));
    }

    public function pay(string $orderNumber, Request $request, GuestContext $guest, ResolveOrderTotals $totals, StartPayment $start): RedirectResponse
    {
        $order = $this->ownedOrder($orderNumber, $request, $guest);
        if ($order->status === OrderStatus::Paid) {
            return redirect()->route('orders.confirmation', $order->number);
        }
        $data = $request->validate(['idempotency_key' => ['required', 'uuid'], 'scenario' => ['required', 'in:success,failure,cancelled']]);
        $order = $totals->handle($order);
        $payment = $start->handle($order, $data['idempotency_key'], $data['scenario']);
        if ($payment->status === PaymentStatus::Succeeded) {
            return redirect()->route('orders.confirmation', $order->number);
        }

        return back()->with('payment_message', $payment->status === PaymentStatus::Failed
            ? 'Payment wasn’t completed. Please try again.' : 'Payment was cancelled. You can try again when you’re ready.');
    }

    public function confirmation(string $orderNumber, Request $request, GuestContext $guest): View
    {
        $order = $this->ownedOrder($orderNumber, $request, $guest)->load(['items.artworkSession', 'items.generationAsset']);
        abort_unless($order->status === OrderStatus::Paid, 404);

        return view('storefront.checkout.confirmation', compact('order'));
    }

    private function ownedOrder(string $orderNumber, Request $request, GuestContext $guest): Order
    {
        $order = Order::query()->where('number', $orderNumber)->firstOrFail();
        abort_unless($guest->owns($order->access_token_hash, $request), 404);

        return $order;
    }
}
