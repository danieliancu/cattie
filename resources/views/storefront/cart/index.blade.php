<x-layouts.storefront title="Your basket | Cattie.uk" description="Review your personalised Cattie.uk gifts.">
<section class="shell py-12 sm:py-20">
    <div class="mx-auto max-w-5xl">
        <p class="eyebrow">Your basket</p><h1 class="mt-3 font-display text-5xl">Made especially for you</h1>
        @if(!$cart || $cart->items->isEmpty())
            <div class="mt-10 rounded-[2rem] bg-white p-10 text-center"><p class="text-muted">Your basket is empty.</p><a href="{{ route('products.index') }}" class="button-primary mt-6">Choose a gift</a></div>
        @else
            <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_20rem]">
                <div class="space-y-5">@foreach($cart->items as $item)
                    <article class="grid gap-5 rounded-[2rem] bg-white p-5 sm:grid-cols-[9rem_1fr]">
                        <img src="{{ route('artwork.assets', [$item->artworkSession->public_id, $item->generationAsset]) }}" alt="Approved {{ $item->artwork_style_name }} artwork" class="aspect-[2/3] w-full rounded-2xl object-cover">
                        <div><h2 class="font-display text-2xl">{{ $item->product_name }}</h2><p class="mt-1 text-sm text-muted">{{ $item->variant_name }} · {{ $item->artwork_style_name }}</p>
                            @foreach($item->personalisation ?? [] as $field)<p class="mt-2 text-sm"><span class="text-muted">{{ $field['label'] ?? $field['key'] ?? 'Personalisation' }}:</span> {{ $field['value'] ?? '' }}</p>@endforeach
                            <div class="mt-5 flex flex-wrap items-center gap-4"><form method="POST" action="{{ route('cart.quantity', $item) }}" class="flex items-center gap-2">@csrf @method('PATCH')<label for="quantity-{{ $item->id }}" class="text-sm font-bold">Quantity</label><select id="quantity-{{ $item->id }}" name="quantity" onchange="this.form.submit()" class="rounded-xl border border-rose/30 bg-white px-3 py-2">@for($n=1;$n<=config('commerce.max_quantity');$n++)<option value="{{ $n }}" @selected($item->quantity===$n)>{{ $n }}</option>@endfor</select></form>
                                <span class="ml-auto font-bold">£{{ number_format($item->lineTotalMinor()/100, 2) }}</span></div>
                            <div class="mt-4 flex gap-5 text-sm"><form method="POST" action="{{ route('cart.change-artwork',$item) }}">@csrf<button class="font-bold text-coral underline">Change artwork</button></form><form method="POST" action="{{ route('cart.remove',$item) }}">@csrf @method('DELETE')<button class="text-muted underline">Remove</button></form></div>
                        </div>
                    </article>
                @endforeach</div>
                <aside class="h-fit rounded-[2rem] bg-white p-7"><h2 class="font-display text-2xl">Summary</h2><div class="mt-5 flex justify-between"><span>Subtotal</span><strong>£{{ number_format($cart->subtotalMinor()/100,2) }}</strong></div><p class="mt-3 text-sm leading-6 text-muted">Shipping and tax will be confirmed before payment.</p><a href="{{ route('checkout.show') }}" class="button-primary mt-7 w-full text-center">Continue to checkout</a></aside>
            </div>
        @endif
    </div>
</section>
</x-layouts.storefront>
