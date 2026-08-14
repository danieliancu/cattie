<x-layouts.storefront title="Checkout details | Cattie.uk" description="Enter delivery details for your personalised gift.">
<section class="shell py-12 sm:py-20">
    <div class="mx-auto max-w-5xl" x-data="{shippingMethodId:@js(old('shipping_method_id', $selectedShippingMethodId)), methods:@js($availableShippingMethods->mapWithKeys(fn($method)=>[$method->id=>['price_minor'=>$method->price_minor,'name'=>$method->name]])), subtotal:@js($cart->subtotalMinor()), shippingPrice(){return this.methods[this.shippingMethodId]?.price_minor ?? 0}, money(value){return new Intl.NumberFormat('en-GB',{style:'currency',currency:'GBP'}).format(value/100)}}">
        <div class="grid items-start gap-8 lg:grid-cols-[1fr_20rem]">
            <div>
                <p class="eyebrow">Checkout</p><h1 class="mt-3 font-display text-5xl">Where should we send it?</h1><p class="mt-4 text-muted">No payment is taken at this stage.</p>
                <form id="checkout-details-form" method="POST" action="{{ route('checkout.store') }}" class="mt-10 grid gap-5 rounded-[2rem] bg-white p-6 sm:grid-cols-2 sm:p-9">@csrf
                    <input type="hidden" name="pricing_hash" value="{{ $cart->pricing_hash }}"><input type="hidden" name="checkout_idempotency_key" value="{{ $checkoutKey }}">
                    @foreach([['first_name','First name',true],['last_name','Last name',true],['email','Email address',true],['phone','Phone (optional)',false],['address_line_1','Address line 1',true],['address_line_2','Address line 2 (optional)',false],['city','Town or city',true],['county','County (optional)',false],['postcode','Postcode',true]] as [$name,$label,$required])<label class="block {{ in_array($name,['email','address_line_1','address_line_2'])?'sm:col-span-2':'' }}"><span class="text-sm font-bold">{{ $label }}</span><input name="{{ $name }}" value="{{ old($name, $name === 'email' ? auth()->user()?->email : null) }}" @required($required) class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3"></label>@endforeach
                    <input type="hidden" name="country" value="GB"><p class="text-sm text-muted sm:col-span-2">Delivery country: United Kingdom</p>
                    <fieldset class="sm:col-span-2">
                        <legend class="font-display text-xl">Delivery method</legend>
                        @if($checkoutBlocked)
                            <div class="mt-4 rounded-2xl bg-rose/20 p-4 text-sm text-red-800" role="alert">These products do not share a delivery method yet. Please order products from different fulfilment providers separately.</div>
                        @else
                            <div class="mt-4 space-y-3">
                                @foreach($availableShippingMethods as $method)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-rose/25 bg-cream p-4 has-[:checked]:border-coral has-[:checked]:ring-2 has-[:checked]:ring-coral/20">
                                        <input type="radio" name="shipping_method_id" value="{{ $method->id }}" x-model="shippingMethodId" class="mt-1" required>
                                        <span class="min-w-0 flex-1"><strong class="block">{{ $method->name }}</strong><span class="mt-1 block text-sm text-muted">{{ $method->estimateLabel() }}</span></span>
                                        <strong>£{{ number_format($method->price_minor/100,2) }}</strong>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                </form>
            </div>
            <aside class="h-fit rounded-[2rem] bg-white p-7 lg:sticky lg:top-24">
                <h2 class="font-display text-2xl">Order summary</h2>
                @foreach($cart->items as $item)
                    <div class="mt-5 border-t border-rose/20 pt-5">
                        <div class="grid grid-cols-[3.5rem_1fr] gap-3">
                            <img src="{{ route('artwork.assets', [$item->artworkSession->public_id, $item->generationAsset]) }}" class="h-16 w-12 rounded-lg object-cover" alt="Approved artwork">
                            <div class="min-w-0"><strong class="block leading-5">{{ $item->product_name }} <span class="whitespace-nowrap">£{{ number_format($item->unit_price_minor/100,2) }} each</span></strong><p class="mt-1 text-sm leading-5 text-muted">{{ $item->variant_name }} · {{ $item->artwork_style_name === 'Storybook Cartoon' ? 'Cartoon' : $item->artwork_style_name }} · Qty {{ $item->quantity }}</p></div>
                        </div>
                        <p class="mt-3 text-right font-bold">£{{ number_format($item->lineTotalMinor()/100,2) }}</p>
                    </div>
                @endforeach
                <dl class="mt-5 space-y-3 border-t border-rose/20 pt-5">
                    <div class="flex justify-between gap-4"><dt>Subtotal</dt><dd>£{{ number_format($cart->subtotalMinor()/100,2) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>UK delivery</dt><dd x-text="money(shippingPrice())"></dd></div>
                    <div class="flex justify-between gap-4"><dt>Tax <span class="text-xs text-muted">(provisional)</span></dt><dd>£0.00</dd></div>
                    <div class="flex justify-between gap-4 text-xl font-bold"><dt>Total</dt><dd x-text="money(subtotal + shippingPrice())"></dd></div>
                </dl>
                <button type="submit" form="checkout-details-form" class="button-primary mt-7 w-full text-center disabled:cursor-not-allowed disabled:opacity-50" @disabled($checkoutBlocked)>Continue to payment</button>
            </aside>
        </div>
    </div>
</section>
</x-layouts.storefront>
