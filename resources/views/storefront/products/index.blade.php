<x-layouts.storefront title="Personalised gifts | Kattie.uk" description="Browse personalised family, children and pet gifts created from your favourite photographs." :canonical="$canonical" :robots="$robots">
    <section class="shell py-12 sm:py-20">
        <div class="max-w-2xl"><p class="eyebrow hidden sm:block">The Kattie collection</p><h1 class="font-display text-2xl sm:mt-4 sm:text-6xl">A favourite photo, made unforgettable.</h1><x-expandable-text text="Choose the gift first. We’ll help you turn your photograph into artwork you love before it’s ever made." /></div>
        @if($categories->isNotEmpty())
            <nav class="sm:mt-10" aria-labelledby="shop-by-category">
                <h2 id="shop-by-category" class="hidden font-display text-2xl sm:block">Shop by category</h2>
                <div class="sticky top-[116px] z-30 -mx-4 mt-4 flex flex-nowrap gap-3 overflow-x-auto bg-white/95 px-4 py-3 backdrop-blur [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mx-0 sm:px-0 lg:top-[77px] lg:flex-wrap lg:overflow-visible">@foreach($categories as $category)<a href="{{ $category->url() }}" class="shrink-0 rounded-full border border-rose/30 bg-white px-5 py-2.5 text-sm font-bold transition hover:border-coral hover:text-coral">{{ $category->name }}</a>@endforeach</div>
            </nav>
        @endif
        <div class="grid grid-cols-2 gap-x-4 gap-y-10 sm:mt-14 sm:gap-x-8 sm:gap-y-14 lg:grid-cols-3">@forelse($products as $product)<x-product-card :product="$product" />@empty<p>No gifts are available just now. Please check back soon.</p>@endforelse</div>
        @if($products->hasPages())<div class="mt-16">{{ $products->links() }}</div>@endif
    </section>
</x-layouts.storefront>
