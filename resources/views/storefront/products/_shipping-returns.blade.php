@php($policy = config('commercial-policies'))
<div class="mt-10 border-t border-rose/25 pt-8">
    <div class="grid grid-cols-3 overflow-hidden rounded-2xl border border-rose/25 bg-white">
        <div class="flex min-w-0 flex-col items-center gap-2 border-r border-rose/20 px-2 py-4 text-center sm:flex-row sm:pl-4 sm:text-left">
            <i data-lucide="heart" class="h-7 w-7 shrink-0 text-coral" aria-hidden="true"></i>
            <span class="text-sm font-bold leading-tight">Made<br>For You</span>
        </div>
        <div class="flex min-w-0 flex-col items-center gap-2 border-r border-rose/20 px-2 py-4 text-center sm:flex-row sm:pl-4 sm:text-left">
            <i data-lucide="lock-keyhole" class="h-7 w-7 shrink-0 text-coral" aria-hidden="true"></i>
            <span class="text-sm font-bold leading-tight">Secure<br>Shopping</span>
        </div>
        <div class="flex min-w-0 flex-col items-center gap-2 px-2 py-4 text-center sm:flex-row sm:pl-4 sm:text-left">
            <i data-lucide="shield-check" class="h-7 w-7 shrink-0 text-coral" aria-hidden="true"></i>
            <span class="text-sm font-bold leading-tight">Privacy<br>Guaranteed</span>
        </div>
    </div>
</div>

<div class="mt-10 border-t border-rose/25 pt-8">
    <h2 class="font-display text-2xl">Shipping &amp; Returns</h2>

    <div class="mt-8">
        <h3 class="text-sm font-bold leading-tight">Personalised Item Returns</h3>
        <p class="mt-4 leading-7 text-muted">{{ $policy['returns_summary'] }}</p>
        <a class="mt-4 inline-block font-semibold text-coral underline decoration-coral/40 underline-offset-4" href="{{ route('information.returns') }}">Read our full Returns Policy</a>
    </div>

    <div class="mt-8">
        <h3 class="text-sm font-bold leading-tight">Delivery</h3>
        @include('storefront.information._delivery-table', ['productionHeading' => $policy['production_heading'], 'productionMessage' => $policy['production_message'], 'methods' => $policy['delivery_methods'], 'disclaimer' => $policy['delivery_disclaimer']])
    </div>
</div>
