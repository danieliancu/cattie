<x-layouts.storefront title="My Account | Kattie.uk" description="See your Kattie orders.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-5xl">
    <p class="eyebrow">My Account</p><h1 class="mt-3 font-display text-5xl">Hello</h1><p class="mt-4 text-muted">{{ auth()->user()->email }}</p>
    @include('storefront.account._nav')
    <div class="mt-10 rounded-[2rem] bg-white p-7 sm:p-9"><div class="flex items-center justify-between gap-4"><h2 class="font-display text-3xl">Your orders</h2>@if($orders->isNotEmpty())<a class="font-bold text-coral" href="{{ route('account.orders.index') }}">View all orders</a>@endif</div>
        @if($orders->isEmpty())<p class="mt-6 text-muted">No orders yet</p><a class="button-primary mt-6" href="{{ route('products.index') }}">Shop personalised gifts</a>
        @else<div class="mt-6 divide-y divide-rose/20">@foreach($orders as $order)<a href="{{ route('account.orders.show', $order->number) }}" class="grid gap-2 py-5 first:pt-0 sm:grid-cols-[1fr_auto] sm:items-center"><div><strong>{{ $order->number }}</strong><p class="mt-1 text-sm text-muted">{{ ($order->placed_at ?? $order->created_at)->format('j F Y') }} · <x-order-status-pill :status="$order->status" /> · {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}</p></div><strong>£{{ number_format(($order->total_minor ?? 0) / 100, 2) }}</strong></a>@endforeach</div>@endif
    </div>
</div></section>
</x-layouts.storefront>
