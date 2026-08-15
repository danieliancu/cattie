<x-layouts.storefront :title="'Payment for '.$order->number.' | Kattie.uk'" description="Review the final total for your Kattie.uk order.">
<section class="shell py-12 sm:py-20">
    <div class="mx-auto max-w-6xl">
        <p class="eyebrow">Order {{ $order->number }}</p>
        <h1 class="mt-3 font-display text-5xl">Complete your order</h1>
        <p class="mt-5 leading-7 text-muted">Your artwork and final order total are ready.</p>

        @if(session('payment_message') || $paymentMessage)
            <div class="mt-6 rounded-2xl bg-rose/20 p-4" role="alert">{{ session('payment_message') ?? $paymentMessage }}</div>
        @endif

        <div class="mt-10 flex flex-col gap-7 lg:flex-row lg:items-start">
            <div class="w-full rounded-[2rem] bg-white p-7 lg:flex-1">
                <h2 class="font-display text-2xl">Order summary</h2>
                @foreach($order->items as $item)
                    <div class="mt-5 grid grid-cols-[4rem_1fr_auto] items-center gap-4 border-t border-rose/20 pt-5">
                        <img src="{{ route('artwork.assets', [$item->artworkSession->public_id, $item->generationAsset]) }}" class="h-20 w-14 rounded-lg object-cover" alt="Approved artwork">
                        <div>
                            <strong>{{ $item->product_name }}</strong>
                            <p class="text-sm text-muted">{{ $item->variant_name }} · {{ $item->artwork_style_name === 'Storybook Cartoon' ? 'Cartoon' : $item->artwork_style_name }} · Qty {{ $item->quantity }}</p>
                        </div>
                        <strong>£{{ number_format($item->total_price_minor / 100, 2) }}</strong>
                    </div>
                @endforeach
                <dl class="mt-6 space-y-3 border-t border-rose/20 pt-5">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>£{{ number_format($order->subtotal_minor / 100, 2) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>{{ data_get($order->shipping_method_snapshot, 'name', 'UK delivery') }}</dt><dd>£{{ number_format($order->shipping_minor / 100, 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Tax <span class="text-xs text-muted">(provisional)</span></dt><dd>£{{ number_format($order->tax_minor / 100, 2) }}</dd></div>
                    <div class="flex justify-between text-xl font-bold"><dt>Total</dt><dd>£{{ number_format($order->total_minor / 100, 2) }}</dd></div>
                </dl>
            </div>

            @if($fakeEnabled)
                <div class="w-full rounded-[2rem] border-2 border-dashed border-coral/40 bg-sand p-6 lg:flex-1">
                    <p class="eyebrow">Development payment simulator</p>
                    <p class="mt-2 text-sm text-muted">No real payment will be taken.</p>
                    <form method="POST" action="{{ route('checkout.pay', $order->number) }}" class="mt-5 flex flex-wrap gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $paymentKey }}">
                        <button name="scenario" value="success" class="button-primary">Complete test payment</button>
                        <button name="scenario" value="failure" class="rounded-full border border-coral px-5 py-3 font-bold">Fail</button>
                        <button name="scenario" value="cancelled" class="rounded-full px-5 py-3 font-bold text-muted underline">Cancel</button>
                    </form>
                </div>
            @elseif($stripeEnabled)
                <div class="w-full rounded-[2rem] bg-white p-4 sm:p-7 lg:flex-1" x-data="embeddedCheckout(@js(['publishableKey' => $stripePublishableKey, 'sessionUrl' => route('checkout.stripe-session', $order->number), 'statusUrl' => route('checkout.stripe-status', $order->number), 'idempotencyKey' => $paymentKey, 'csrf' => csrf_token()]))" x-init="start()" @beforeunload.window="destroyCheckout()">
                    <div x-show="loading" class="space-y-4" aria-live="polite">
                        <p class="font-bold">Loading secure payment…</p>
                        <div class="h-12 animate-pulse rounded-xl bg-sand"></div>
                        <div class="h-12 animate-pulse rounded-xl bg-sand"></div>
                        <div class="h-24 animate-pulse rounded-xl bg-sand"></div>
                    </div>
                    <div x-show="confirming" x-cloak class="rounded-2xl bg-sand p-6 text-center" aria-live="polite">
                        <p class="font-display text-2xl">We’re confirming your payment</p>
                        <p class="mt-2 text-sm text-muted">Please keep this page open. This usually takes only a few seconds.</p>
                    </div>
                    <div x-show="error" x-cloak class="rounded-2xl bg-rose/20 p-5" role="alert">
                        <p x-text="error"></p>
                        <button type="button" class="button-primary mt-4" @click="retry()">Try again</button>
                    </div>
                    <div id="stripe-embedded-checkout" x-show="!loading && !confirming && !error"></div>
                </div>
            @else
                <div class="w-full rounded-2xl bg-sand p-5 lg:flex-1">Payment is not currently available.</div>
            @endif
        </div>
    </div>
</section>
</x-layouts.storefront>
