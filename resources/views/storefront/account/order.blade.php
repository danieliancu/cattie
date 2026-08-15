<x-layouts.storefront :title="$order->number.' | My Orders | Kattie.uk'" description="Your Kattie order details.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-5xl"><p class="eyebrow">My Account</p><h1 class="mt-3 font-display text-5xl">Order {{ $order->number }}</h1><p class="mt-4 text-muted">{{ ($order->placed_at ?? $order->created_at)->format('j F Y') }} · {{ $order->status->customerLabel() }}</p>@include('storefront.account._nav')
<div class="mt-10 grid items-start gap-7 lg:grid-cols-[1fr_20rem]">
    <div class="rounded-[2rem] bg-white p-7 sm:p-9"><h2 class="font-display text-3xl">Items</h2>
        @foreach($order->items as $item)<article class="mt-6 grid grid-cols-[5rem_1fr] gap-4 border-t border-rose/20 pt-6 sm:grid-cols-[6rem_1fr_auto]">
            <img src="{{ route('account.orders.artwork', [$order->number, $item]) }}" alt="Approved {{ $item->product_name }} artwork" class="aspect-[4/5] w-full rounded-xl bg-sand object-contain">
            <div><h3 class="font-bold">{{ $item->product_name }}</h3>@if($item->variant_name)<p class="mt-1 text-sm text-muted">{{ $item->variant_name }}</p>@endif @if($item->artwork_style_name)<p class="text-sm text-muted">{{ $item->artwork_style_name === 'Storybook Cartoon' ? 'Cartoon' : $item->artwork_style_name }}</p>@endif
                @foreach($item->personalisation ?? [] as $field)@if(($field['value'] ?? null) !== null && ($field['value'] ?? '') !== '')<p class="mt-1 text-sm"><span class="text-muted">{{ $field['label'] ?? $field['key'] ?? 'Personalisation' }}:</span> {{ $field['value'] }}</p>@endif @endforeach
                <p class="mt-2 text-sm text-muted">Qty {{ $item->quantity }} · £{{ number_format($item->unit_price_minor / 100, 2) }} each</p>
            </div><strong class="col-start-2 sm:col-start-auto">£{{ number_format($item->total_price_minor / 100, 2) }}</strong>
        </article>@endforeach
    </div>
    <aside class="rounded-[2rem] bg-white p-7"><h2 class="font-display text-2xl">Order summary</h2><dl class="mt-5 space-y-3"><div class="flex justify-between"><dt>Subtotal</dt><dd>£{{ number_format($order->subtotal_minor / 100, 2) }}</dd></div><div class="flex justify-between gap-4"><dt>{{ data_get($order->shipping_method_snapshot, 'name', 'Delivery') }}</dt><dd>£{{ number_format(($order->shipping_minor ?? 0) / 100, 2) }}</dd></div>@if(($order->tax_minor ?? 0) > 0)<div class="flex justify-between"><dt>Tax</dt><dd>£{{ number_format($order->tax_minor / 100, 2) }}</dd></div>@endif<div class="flex justify-between border-t border-rose/20 pt-4 text-xl font-bold"><dt>Total</dt><dd>£{{ number_format(($order->total_minor ?? 0) / 100, 2) }}</dd></div></dl>
        @php($address = $order->shipping_address)<h2 class="mt-8 font-display text-2xl">Delivery address</h2><address class="mt-4 text-sm not-italic leading-6">{{ $address['first_name'] }} {{ $address['last_name'] }}<br>{{ $address['address_line_1'] }}@if($address['address_line_2'] ?? null)<br>{{ $address['address_line_2'] }}@endif<br>{{ $address['city'] }}@if($address['county'] ?? null), {{ $address['county'] }}@endif<br>{{ $address['postcode'] }}</address>
    </aside>
</div></div></section>
</x-layouts.storefront>
