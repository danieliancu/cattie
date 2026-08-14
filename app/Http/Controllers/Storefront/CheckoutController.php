<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Cart\Actions\CreateOrderFromCart;
use App\Domain\Cart\Actions\AbandonCartCheckout;
use App\Domain\Cart\Actions\RefreshCartPrices;
use App\Domain\Cart\Actions\ResolveGuestCart;
use App\Domain\Payments\Actions\ResolveOrderTotals;
use App\Domain\Payments\Actions\ResolveEligibleShippingMethods;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\Actions\StartEmbeddedCheckout;
use App\Domain\Payments\Actions\ReconcilePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Providers\Payments\StripePaymentProvider;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, ResolveGuestCart $resolve, RefreshCartPrices $prices, RecordAnalyticsEvent $analytics, ResolveEligibleShippingMethods $shippingMethods): View|RedirectResponse
    {
        [$cart] = $resolve->handle($request, false);
        if (! $cart || ! $cart->items()->exists()) {
            return redirect()->route('cart.index');
        }
        if (! $cart->converted_order_id) {
            $prices->handle($cart);
        }
        $cart->load(['items.artworkSession', 'items.generationAsset', 'items.variant.fulfilmentMappings', 'convertedOrder.shippingMethod']);
        $availableShippingMethods = $shippingMethods->handle($cart);
        $selectedShippingMethodId = $cart->convertedOrder?->shipping_method_id;
        if ($selectedShippingMethodId && $cart->convertedOrder?->shipping_method_snapshot) {
            $snapshot = $cart->convertedOrder->shipping_method_snapshot;
            $frozenMethod = new ShippingMethod([
                'provider' => $snapshot['provider'],
                'code' => $snapshot['code'],
                'name' => $snapshot['name'],
                'provider_service_code' => $snapshot['provider_service_code'],
                'price_minor' => $snapshot['price_minor'],
                'currency' => $snapshot['currency'],
                'country' => $snapshot['country'],
                'estimated_business_days_min' => $snapshot['estimated_business_days_min'],
                'estimated_business_days_max' => $snapshot['estimated_business_days_max'],
                'is_active' => true,
            ]);
            $frozenMethod->id = $selectedShippingMethodId;
            $availableShippingMethods = $availableShippingMethods
                ->reject(fn (ShippingMethod $method): bool => $method->id === $selectedShippingMethodId)
                ->prepend($frozenMethod)
                ->values();
        }
        $selectedShippingMethodId ??= $availableShippingMethods->first()?->id;
        $checkoutBlocked = $availableShippingMethods->isEmpty();
        $analytics->handle('checkout_started', $cart);
        $checkoutKey = (string) Str::uuid();

        return view('storefront.checkout.show', compact('cart', 'checkoutKey', 'availableShippingMethods', 'selectedShippingMethodId', 'checkoutBlocked'));
    }

    public function store(CheckoutRequest $request, ResolveGuestCart $resolve, CreateOrderFromCart $create, GuestContext $guest, AbandonCartCheckout $abandonCheckout): RedirectResponse
    {
        [$cart] = $resolve->handle($request, false);
        if (! $cart && $guest->token($request)) {
            $cart = Cart::query()
                ->where('access_token_hash', $guest->hash($guest->token($request)))
                ->whereNotNull('converted_order_id')->latest()->first();
        }
        abort_unless($cart, 404);
        $customer = $request->customer();
        if ($cart->converted_order_id) {
            $existing = $cart->convertedOrder;
            $matches = $existing
                && $existing->shipping_method_id === $request->validated('shipping_method_id')
                && $existing->email === $customer['email']
                && ($existing->phone ?? null) === ($customer['phone'] ?? null)
                && $existing->shipping_address === $customer['shipping_address'];
            if (! $matches) {
                $abandonCheckout->handle($cart);
                $cart->refresh();
            }
        }
        $order = $create->handle($cart, $request->validated('pricing_hash'), $customer, $request->validated('checkout_idempotency_key'), $request->validated('shipping_method_id'));

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
        $stripeEnabled = config('payments.provider') === 'stripe';
        $stripePublishableKey = (string) config('payments.stripe.publishable_key');
        $paymentKey = (string) Str::uuid();
        $paymentMessage = $request->query('payment') === 'cancelled' ? 'Payment was not completed. You can try again when you’re ready.' : null;

        return view('storefront.checkout.payment', compact('order', 'fakeEnabled', 'stripeEnabled', 'stripePublishableKey', 'paymentKey', 'paymentMessage'));
    }

    public function pay(string $orderNumber, Request $request, GuestContext $guest, ResolveOrderTotals $totals, StartPayment $start): RedirectResponse
    {
        $order = $this->ownedOrder($orderNumber, $request, $guest);
        if ($order->status === OrderStatus::Paid) {
            return redirect()->route('orders.confirmation', $order->number);
        }
        $rules = ['idempotency_key' => ['required', 'uuid']];
        if (config('payments.provider') === 'fake') {
            $rules['scenario'] = ['required', 'in:success,failure,cancelled'];
        }
        $data = $request->validate($rules);
        $order = $totals->handle($order);
        $payment = $start->handle($order, $data['idempotency_key'], $data['scenario'] ?? 'success');
        if ($payment->status === PaymentStatus::Succeeded) {
            return redirect()->route('orders.confirmation', $order->number);
        }
        return back()->with('payment_message', $payment->status === PaymentStatus::Failed
            ? 'Payment wasn’t completed. Please try again.' : 'Payment was cancelled. You can try again when you’re ready.');
    }

    public function stripeSession(string $orderNumber, Request $request, GuestContext $guest, ResolveOrderTotals $totals, StartEmbeddedCheckout $start): JsonResponse
    {
        abort_unless(config('payments.provider') === 'stripe', 404);
        $order = $this->ownedOrder($orderNumber, $request, $guest);
        if ($order->status === OrderStatus::Paid) {
            return response()->json(['status' => 'paid', 'confirmation_url' => route('orders.confirmation', $order->number)]);
        }
        $idempotencyKey = $request->validate(['idempotency_key' => ['required', 'uuid']])['idempotency_key'];

        try {
            $result = $start->handle($totals->handle($order), $idempotencyKey);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => "We couldn't start your payment. Please try again."], 422);
        }

        if ($result['payment']->status === PaymentStatus::Succeeded) {
            return response()->json(['status' => 'paid', 'confirmation_url' => route('orders.confirmation', $order->number)]);
        }

        return response()->json(['status' => 'pending', 'client_secret' => $result['client_secret']]);
    }

    public function stripeStatus(string $orderNumber, Request $request, GuestContext $guest, StripePaymentProvider $stripe, ReconcilePayment $reconcile): JsonResponse
    {
        abort_unless(config('payments.provider') === 'stripe', 404);
        $order = $this->ownedOrder($orderNumber, $request, $guest);
        if ($order->status === OrderStatus::Paid) {
            return response()->json(['status' => 'paid', 'confirmation_url' => route('orders.confirmation', $order->number)]);
        }
        $payment = $order->payments()->where('provider', 'stripe')->whereNotNull('external_id')->latest()->firstOrFail();

        try {
            $payment = $reconcile->handle($stripe->retrieve($payment->external_id), $payment);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'pending']);
        }

        return response()->json([
            'status' => $payment->status === PaymentStatus::Succeeded ? 'paid' : $payment->status->value,
            'confirmation_url' => $payment->status === PaymentStatus::Succeeded ? route('orders.confirmation', $order->number) : null,
        ]);
    }

    public function stripeReturn(string $orderNumber, Request $request, GuestContext $guest, StripePaymentProvider $stripe, ReconcilePayment $reconcile): RedirectResponse
    {
        $order = $this->ownedOrder($orderNumber, $request, $guest);
        $sessionId = $request->validate(['session_id' => ['required', 'string', 'max:255']])['session_id'];
        $payment = Payment::query()->where('order_id', $order->id)->where('provider', 'stripe')->where('external_id', $sessionId)->firstOrFail();

        try {
            $payment = $reconcile->handle($stripe->retrieve($sessionId), $payment);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('checkout.payment', $order->number)->with('payment_message', 'We are still confirming your payment. Please check again shortly.');
        }

        return $payment->status === PaymentStatus::Succeeded
            ? redirect()->route('orders.confirmation', $order->number)
            : redirect()->route('checkout.payment', $order->number)->with('payment_message', 'We are still confirming your payment. Please check again shortly.');
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
        abort_unless($guest->owns($order->access_token_hash, $request) || ($request->user() && $order->user_id === $request->user()->id), 404);

        return $order;
    }
}
