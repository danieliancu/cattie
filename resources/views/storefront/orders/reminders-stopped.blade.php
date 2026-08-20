<x-layouts.storefront title="Reminders stopped | Kattie.uk" description="You won't receive further reminders about this order.">
<section class="shell py-16 sm:py-24"><div class="mx-auto max-w-xl text-center">
    <p class="eyebrow">Order {{ $order->number }}</p>
    <h1 class="mt-3 font-display text-5xl">Reminders stopped</h1>
    <p class="mt-5 leading-7 text-muted">You won't receive any further reminders about this order. If you'd still like to finish it, you're always welcome back.</p>
    <a class="button-primary mt-8" href="{{ route('products.index') }}">Browse gifts</a>
</div></section>
</x-layouts.storefront>
