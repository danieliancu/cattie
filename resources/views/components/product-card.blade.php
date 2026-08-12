@props(['product'])
<article class="group min-w-0">
    <a href="{{ route('products.show', $product->slug) }}" class="block focus:outline-none">
        <div class="aspect-square w-full overflow-hidden rounded-2xl bg-sand ring-1 ring-ink/5 transition group-hover:-translate-y-1 group-hover:shadow-xl group-focus-within:ring-2 group-focus-within:ring-coral sm:h-80 sm:aspect-auto sm:rounded-[2rem]">
            @if($image = $product->primaryImage())
                <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
            @endif
        </div>
        <div class="px-0.5 pt-3 sm:px-1 sm:pt-5">
            <h3 class="line-clamp-2 font-display text-lg leading-tight group-hover:text-coral sm:text-2xl">{{ $product->name }}</h3>
            <p class="mt-2 hidden line-clamp-2 text-sm leading-6 text-muted sm:block">{{ $product->short_description }}</p>
            <div class="mt-2 flex flex-col items-start gap-1 sm:hidden">
                <span class="text-sm font-bold text-coral">Create yours <span aria-hidden="true">→</span></span>
                <span class="text-sm font-semibold">From {{ $product->formattedPrice() }}</span>
            </div>
            <div class="mt-4 hidden items-center justify-between sm:flex"><span class="font-semibold">From {{ $product->formattedPrice() }}</span><span class="text-sm font-bold text-coral">Create yours <span aria-hidden="true">→</span></span></div>
        </div>
    </a>
</article>
