<x-layouts.storefront title="Your basket | Kattie.uk" description="Review your personalised Kattie.uk gifts.">
<section class="shell py-12 sm:py-20">
    <div class="mx-auto max-w-5xl">
        <p class="eyebrow">Your basket</p><h1 class="mt-3 font-display text-5xl">Made especially for you</h1>
        @if(!$cart || $cart->items->isEmpty())
            <div class="mt-10 rounded-[2rem] bg-white p-10 text-center"><p class="text-muted">Your basket is empty.</p><a href="{{ route('products.index') }}" class="button-primary mt-6">Choose a gift</a></div>
        @else
            <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_20rem]">
                <div class="space-y-5">@foreach($cart->items as $item)
                    <article class="grid gap-5 rounded-[2rem] border-b-4 border-rose/30 bg-white p-5 last:border-b-0 sm:grid-cols-[11rem_1fr] sm:border-b sm:border-rose/25">
                        <div class="flex h-auto w-full max-w-full items-center justify-center overflow-hidden rounded-2xl sm:h-52 sm:w-44 sm:p-2" @if($item->composedDesign) style="background-color: {{ $item->composedDesign->previewSurfaceColour() }}" @endif>
                            <img src="{{ $item->composedDesign ? route('artwork.designs', [$item->artworkSession->public_id, $item->composedDesign]) : route('artwork.assets', [$item->artworkSession->public_id, $item->generationAsset]) }}" alt="Approved {{ $item->artwork_style_name === 'Storybook Cartoon' ? 'Cartoon' : $item->artwork_style_name }} design" class="h-auto w-full object-contain sm:h-full">
                        </div>
                        @php($productThumbnail = $item->productThumbnail())
                        <div><h2 class="font-display text-2xl">{{ $item->product_name }}</h2><p class="mt-1 text-sm text-muted">{{ $item->variant_name }} · {{ $item->artwork_style_name === 'Storybook Cartoon' ? 'Cartoon' : $item->artwork_style_name }}</p>
                            @foreach($item->personalisation ?? [] as $field)<p class="mt-2 text-sm"><span class="text-muted">{{ $field['label'] ?? $field['key'] ?? 'Personalisation' }}:</span> {{ $field['value'] ?? '' }}</p>@endforeach
                            <div class="relative -top-[50px] -mb-[50px] sm:-top-[70px] sm:-mb-[70px]">
                                <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
                                    <form method="POST" action="{{ route('cart.quantity', $item) }}" class="flex items-center gap-2 lg:relative">@csrf @method('PATCH')<label for="quantity-{{ $item->id }}" class="text-sm font-bold">Quantity</label><select id="quantity-{{ $item->id }}" name="quantity" onchange="this.form.submit()" class="rounded-xl border border-rose/30 bg-white px-3 py-2">@for($n=1;$n<=config('commerce.max_quantity');$n++)<option value="{{ $n }}" @selected($item->quantity===$n)>{{ $n }}</option>@endfor</select></form>
                                    @if($productThumbnail)<div class="flex h-auto w-[100px] items-center justify-center overflow-hidden rounded-xl p-1.5" title="{{ $item->variant_name }}"><img src="{{ $productThumbnail->url() }}" alt="{{ $item->variant_name }} product" class="h-auto w-full object-contain"></div>@endif
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-5 text-sm lg:mt-10"><form method="POST" action="{{ route('cart.change-artwork',$item) }}">@csrf<button class="cursor-pointer font-bold text-coral underline">Change artwork</button></form><form method="POST" action="{{ route('cart.remove',$item) }}">@csrf @method('DELETE')<button class="cursor-pointer text-muted underline">Remove</button></form><span class="ml-auto w-[100px] shrink-0 text-center text-base font-bold">£{{ number_format($item->lineTotalMinor()/100, 2) }}</span></div>
                            </div>
                        </div>
                    </article>
                @endforeach</div>
                <aside class="h-fit rounded-[2rem] bg-white p-7"><h2 class="font-display text-2xl">Summary</h2><div class="mt-5 flex justify-between"><span>Subtotal</span><strong>£{{ number_format($cart->subtotalMinor()/100,2) }}</strong></div><p class="mt-3 text-sm leading-6 text-muted">Shipping and tax will be confirmed before payment.</p><a href="{{ route('checkout.show') }}" class="button-primary mt-7 w-full text-center">Continue to checkout</a></aside>
            </div>
        @endif
    </div>
</section>
</x-layouts.storefront>
