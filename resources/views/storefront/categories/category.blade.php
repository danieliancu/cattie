<x-layouts.storefront :title="$category->meta_title ?: $category->name.' | Personalised Gifts | Kattie.uk'" :description="$category->meta_description ?: $category->short_description" :canonical="$canonical">
    <nav class="shell pt-10 text-sm text-muted" aria-label="Breadcrumb"><ol class="flex flex-wrap items-center gap-2"><li><a class="hover:text-coral" href="{{ route('home') }}">Home</a></li><li aria-hidden="true">→</li><li aria-current="page">{{ $category->name }}</li></ol></nav>

    <section class="shell py-4 sm:py-20">
        <div class="max-w-2xl"><p class="eyebrow hidden sm:block">Personalised gifts</p><h1 class="font-display text-2xl sm:mt-4 sm:text-6xl">{{ $category->name }}</h1>@if($category->short_description)<x-expandable-text :text="$category->short_description" />@endif</div>

        @if($category->children->isNotEmpty())
            <nav class="sm:mt-10" aria-labelledby="shop-{{ $category->slug }}">
                <h2 id="shop-{{ $category->slug }}" class="hidden font-display text-2xl sm:block">Shop {{ $category->name }}</h2>
                <div class="sticky top-[116px] z-30 -mx-4 mt-4 flex flex-nowrap gap-3 overflow-x-auto bg-white/95 px-4 py-3 backdrop-blur [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mx-0 sm:px-0 lg:top-[77px] lg:flex-wrap lg:overflow-visible">@foreach($category->children as $child)<a href="{{ $child->url() }}" class="shrink-0 rounded-full border border-rose/30 bg-white px-5 py-2.5 text-sm font-bold transition hover:border-coral hover:text-coral">{{ $child->name }}</a>@endforeach</div>
            </nav>
        @endif

        @if($featuredProducts->isNotEmpty())
            <div class="grid grid-cols-2 gap-x-4 gap-y-10 sm:mt-14 sm:gap-x-8 sm:gap-y-14 lg:grid-cols-3">@foreach($featuredProducts as $product)<x-product-card :product="$product" />@endforeach</div>
        @endif

        @if($category->description)<div class="mt-16 max-w-3xl border-t border-rose/25 pt-10 leading-8 text-muted">{{ $category->description }}</div>@endif
    </section>

    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $category->name, 'item' => $category->url()],
                ]],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</x-layouts.storefront>
