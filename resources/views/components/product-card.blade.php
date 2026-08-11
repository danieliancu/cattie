@props(['product'])
<article class="group">
    <a href="{{ route('products.show', $product->slug) }}" class="block focus:outline-none">
        <div class="aspect-[4/5] overflow-hidden rounded-[2rem] bg-sand ring-1 ring-ink/5 transition group-hover:-translate-y-1 group-hover:shadow-xl group-focus-within:ring-2 group-focus-within:ring-coral">
            @if($image = $product->primaryImage())
                <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
            @endif
        </div>
        <div class="px-1 pt-5">
            <h3 class="font-display text-2xl leading-tight group-hover:text-coral">{{ $product->name }}</h3>
            <p class="mt-2 line-clamp-2 text-sm leading-6 text-muted">{{ $product->short_description }}</p>
            <div class="mt-4 flex items-center justify-between"><span class="font-semibold">From {{ $product->formattedPrice() }}</span><span class="text-sm font-bold text-coral">Create yours <span aria-hidden="true">→</span></span></div>
        </div>
    </a>
</article>
