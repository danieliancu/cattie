<x-layouts.storefront :title="$category->meta_title ?: $category->name.' | Personalised Gifts | Kattie.uk'" :description="$category->meta_description ?: $category->short_description" :canonical="$canonical">
    <nav class="shell pt-10 text-sm text-muted" aria-label="Breadcrumb"><ol class="flex flex-wrap items-center gap-2"><li><a class="hover:text-coral" href="{{ route('home') }}">Home</a></li><li aria-hidden="true">→</li><li><a class="hover:text-coral" href="{{ route('products.index') }}">Shop</a></li><li aria-hidden="true">→</li><li aria-current="page">{{ $category->name }}</li></ol></nav>
    <section class="shell py-12 sm:py-16">
        <div class="hidden max-w-3xl sm:block"><p class="eyebrow">Personalised gifts</p><h1 class="mt-4 font-display text-5xl sm:text-6xl">{{ $category->name }}</h1>@if($category->short_description)<p class="mt-6 text-lg leading-8 text-muted">{{ $category->short_description }}</p>@endif</div>
        <form class="flex items-center gap-3 sm:hidden" method="GET" action="{{ route('categories.show', $category) }}">
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
        @if($category->description)<div class="mt-16 max-w-3xl border-t border-rose/25 pt-10 leading-8 text-muted">{{ $category->description }}</div>@endif
    </section>
    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Shop', 'item' => route('products.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => route('categories.show', $category)],
                ]],
                ['@type' => 'ItemList', 'itemListElement' => collect($products->items())->values()->map(fn($product, $index) => [
                    '@type' => 'ListItem', 'position' => $products->firstItem() + $index, 'name' => $product->name, 'url' => route('products.show', $product->slug),
                ])->all()],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</x-layouts.storefront>
