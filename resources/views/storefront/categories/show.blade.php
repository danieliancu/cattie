<x-layouts.storefront :title="$category->meta_title ?: $category->name.' | Kattie.uk'" :description="$category->meta_description ?: $category->short_description" :canonical="$canonical" :robots="$robots">
    <nav class="shell pt-10 text-sm text-muted" aria-label="Breadcrumb"><ol class="flex flex-wrap items-center gap-2"><li><a class="hover:text-coral" href="{{ route('home') }}">Home</a></li><li aria-hidden="true">→</li><li><a class="hover:text-coral" href="{{ $category->parent->url() }}">{{ $category->parent->name }}</a></li><li aria-hidden="true">→</li><li aria-current="page">{{ $category->name }}</li></ol></nav>
    <section class="shell py-12 sm:py-16">
        {{-- Visible on mobile too: these pages are SEO and paid-search landing
             pages, so hiding the H1 and intro from the majority of visitors —
             and from mobile-first indexing — would defeat their purpose. --}}
        <div class="max-w-3xl"><p class="eyebrow hidden sm:block">{{ $category->parent->name }}</p><h1 class="font-display text-2xl sm:mt-4 sm:text-6xl">{{ $category->name }}</h1>@if($category->short_description)<x-expandable-text :text="$category->short_description" />@endif</div>
        @if($products->isNotEmpty())
            <form class="mt-6 flex items-center gap-3 sm:hidden" method="GET" action="{{ $category->url() }}">
                <label class="shrink-0 text-sm font-bold text-ink" for="category-sort">Sort by</label>
                <select id="category-sort" name="sort" onchange="this.form.submit()" class="min-w-0 flex-1 cursor-pointer rounded-xl border border-rose/30 bg-white px-4 py-3 text-sm font-semibold text-ink">
                    <option value="" @selected($sort === '')>Featured</option>
                    <option value="price-high-low" @selected($sort === 'price-high-low')>Price High to Low</option>
                    <option value="price-low-high" @selected($sort === 'price-low-high')>Price Low to High</option>
                    <option value="under-20" @selected($sort === 'under-20')>Under £20</option>
                    <option value="over-20" @selected($sort === 'over-20')>Over £20</option>
                </select>
            </form>
            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:mt-14 sm:gap-x-8 sm:gap-y-14 lg:grid-cols-3">@foreach($products as $product)<x-product-card :product="$product" />@endforeach</div>
            @if($products->hasPages())<div class="mt-16">{{ $products->links() }}</div>@endif
        @else
            <div class="mt-10 rounded-[1.75rem] border border-rose/25 bg-white/70 px-8 py-12 text-center sm:mt-14">
                <p class="font-display text-2xl">We’re adding this collection soon.</p>
                <p class="mx-auto mt-3 max-w-xl leading-7 text-muted">We’re busy creating these personalised pieces. In the meantime, have a look at everything else we make.</p>
                <a href="{{ route('products.index') }}" class="button-primary mt-7 inline-flex">Browse all gifts</a>
            </div>
        @endif
        @if($category->description)<div class="mt-16 max-w-3xl border-t border-rose/25 pt-10 leading-8 text-muted">{{ $category->description }}</div>@endif
        @if($siblings->isNotEmpty())
            <div class="mt-20 border-t border-rose/25 pt-10">
                <h2 class="font-display text-3xl">You may also like</h2>
                <div class="mt-7 flex flex-wrap gap-3">
                    @foreach($siblings as $sibling)<a href="{{ $sibling->url() }}" class="rounded-full border border-rose/35 bg-white px-5 py-2.5 text-sm font-semibold transition hover:border-coral hover:text-coral">{{ $sibling->name }}</a>@endforeach
                </div>
            </div>
        @endif
    </section>
    @php
        $breadcrumb = ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $category->parent->name, 'item' => $category->parent->url()],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => $category->url()],
        ]];
        $graph = [$breadcrumb];
        if ($products->isNotEmpty()) {
            $graph[] = ['@type' => 'ItemList', 'itemListElement' => collect($products->items())->values()->map(fn($product, $index) => [
                '@type' => 'ListItem', 'position' => $products->firstItem() + $index, 'name' => $product->name, 'url' => route('products.show', $product->slug),
            ])->all()];
        }
        $structuredData = ['@context' => 'https://schema.org', '@graph' => $graph];
    @endphp
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</x-layouts.storefront>
