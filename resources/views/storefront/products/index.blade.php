<x-layouts.storefront title="Personalised gifts | Cattie.uk" description="Browse personalised family, children and pet gifts created from your favourite photographs.">
    <section class="shell py-16 sm:py-20">
        <div class="max-w-2xl"><p class="eyebrow">The Cattie collection</p><h1 class="mt-4 font-display text-5xl sm:text-6xl">A favourite photo, made unforgettable.</h1><p class="mt-6 text-lg leading-8 text-muted">Choose the gift first. We’ll help you turn your photograph into artwork you love before it’s ever made.</p></div>
        <div class="mt-14 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">@forelse($products as $product)<x-product-card :product="$product" />@empty<p>No gifts are available just now. Please check back soon.</p>@endforelse</div>
        @if($products->hasPages())<div class="mt-16">{{ $products->links() }}</div>@endif
    </section>
</x-layouts.storefront>
