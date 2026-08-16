<x-layouts.storefront title="Support request sent | Kattie.uk" description="Your Kattie.uk order support request has been received.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-2xl text-center">
    <p class="eyebrow">Order Support</p>
    <h1 class="mt-3 font-display text-5xl text-green-600">Thanks — we've got it</h1>
    <p class="mt-5 text-lg leading-8 text-muted">We'll review what happened and get back to you.</p>

    <div class="mt-8 inline-block rounded-2xl bg-sand px-8 py-5">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-muted">Your support reference</p>
        <p class="mt-1 font-display text-3xl text-ink">{{ $reference }}</p>
    </div>

    <div class="mt-10">
        @auth
            <a class="button-primary" href="{{ route('account.orders.index') }}">Back to My Orders</a>
        @else
            <a class="button-primary" href="{{ route('products.index') }}">Continue shopping</a>
        @endauth
    </div>
</div></section>
</x-layouts.storefront>
