<x-layouts.storefront title="Kattie.uk — Turn favourite photos into magical gifts" description="Turn a favourite family or pet photo into personalised artwork and a meaningful gift.">
    {{-- ===== PHONE hero (mockup); desktop hero kept untouched below (hidden sm:block) ===== --}}
    <section class="relative overflow-hidden sm:hidden">
        <img src="{{ asset('images/hero-mobil-boy.png') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover" style="object-position: right 30%; transform: translateY(-10px);">
        <div class="relative z-10 px-5 pb-8 pt-6">
            <h1 class="max-w-[16rem] font-display text-[2.5rem] leading-[1.05] text-white">Turn it into <em class="text-white not-italic">something magical.</em></h1>
            <a href="{{ route('products.index') }}" class="button-primary mt-6">Shop personalised gifts <span aria-hidden="true">→</span></a>
            <x-hero-social-proof class="mt-6" />
        </div>
    </section>
    <section class="home-hero relative hidden overflow-hidden bg-cream bg-cover bg-[70%_center] bg-no-repeat sm:block" style="background-image: url('{{ asset('images/hero_desktop.png') }}')">
        <div class="absolute inset-0 bg-gradient-to-r from-cream/85 via-cream/35 to-transparent lg:via-transparent"></div>
        <div class="shell relative flex min-h-[600px] items-center pb-36 pt-10 sm:min-h-[640px] lg:pb-44">
            <div class="relative z-10"><p class="eyebrow">Made from a moment you love</p><h1 class="mt-5 max-w-xl font-display text-[10vw] leading-[1.05] sm:text-6xl sm:leading-[1.02] lg:text-7xl">Turn their favourite photo into <em class="text-coral not-italic">something magical.</em></h1><p class="mt-7 max-w-lg text-lg leading-8 text-muted">We transform your photograph into beautiful personalised artwork, then make it into a gift they’ll want to keep forever.</p><a href="{{ route('products.index') }}" class="button-primary mt-9">Shop personalised gifts <span aria-hidden="true">→</span></a><x-hero-social-proof tone="dark" :avatars="['1', '2', '3', '4', '5']" class="mt-8" /></div>
        </div>
    </section>
    {{-- ===== DESKTOP how-it-works step band — overlaps the hero, white card (hidden sm:block) ===== --}}
    <div id="how-it-works" class="relative z-20 hidden scroll-mt-24 sm:block">
        <div class="shell -mt-24 lg:-mt-28">
            <div class="grid grid-cols-2 gap-6 rounded-[2rem] bg-white px-6 py-8 shadow-[0_20px_60px_-25px_rgba(48,44,42,0.35)] min-[1100px]:flex min-[1100px]:items-start min-[1100px]:gap-4 min-[1100px]:px-10">
                @foreach([
                    ['gift', 'bg-coral/10 text-coral', 'Choose your gift', 'Pick a product that makes them smile.'],
                    ['cloud-upload', 'bg-coral/10 text-coral', 'Upload a photo', 'Add a clear, front-facing photo we can work our magic from.'],
                    ['heart', 'bg-violet-100 text-violet-500', 'See your artwork', 'Your real photo becomes a story cartoon. Review your storybook character preview.'],
                    ['shopping-cart', 'bg-coral/10 text-coral', 'Approve & order', 'Happiness is on the way! Approve your artwork, place your order, and we’ll take care of the rest.'],
                ] as [$icon, $tint, $heading, $copy])
                    <div class="flex flex-1 items-start gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full {{ $tint }}">
                            <i data-lucide="{{ $icon }}" class="h-6 w-6" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h3 class="font-display text-lg leading-tight">{{ $heading }}</h3>
                            <p class="mt-1.5 text-sm leading-6 text-muted">{{ $copy }}</p>
                        </div>
                    </div>
                    @if(!$loop->last)
                        <div class="mt-4 hidden shrink-0 items-center gap-1 self-start min-[1100px]:flex" aria-hidden="true">
                            <span class="h-0 w-6 border-t-2 border-dashed border-coral/40"></span>
                            <i data-lucide="arrow-right" class="h-4 w-4 text-coral/60"></i>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @if($categories->isNotEmpty())
        {{-- ===== PHONE categories with product carousel ===== --}}
        <section class="shell py-14 sm:hidden">
            <div class="text-center"><p class="eyebrow">Find your moment</p><h2 class="mt-1 font-display text-xl">Where would you like to start?</h2></div>
            <div class="mt-8 flex flex-col gap-8">
                @foreach($categories as $category)
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <a href="{{ $category->url() }}" class="font-display text-lg hover:text-coral transition">{{ $category->name }}</a>
                                <a href="{{ $category->url() }}"
                                   aria-label="Shop {{ $category->name }}"
                                   class="shrink-0 text-coral transition hover:text-rose-500">
                                    <i data-lucide="arrow-right" class="h-5 w-5" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                        @if($category->featuredProducts->isNotEmpty())
                            <div class="mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                @foreach($category->featuredProducts as $product)
                                    <div class="w-[42%] shrink-0 snap-start">
                                        <x-product-card :product="$product" />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
        {{-- ===== DESKTOP categories (round tiles, unchanged) ===== --}}
        <section class="shell py-20 hidden sm:block">
            <div class="section-heading"><p class="eyebrow">Find your moment</p><h2>Where would you like to start?</h2></div>
            <div class="mt-12 grid grid-cols-2 justify-items-center gap-y-10 gap-x-5 sm:gap-x-6 lg:grid-cols-4">
                @foreach($categories as $category)
                    @php($tile = $category->cardImage())
                    <a href="{{ $category->url() }}" class="group flex w-full max-w-[18rem] flex-col items-center text-center">
                        <div class="aspect-square w-full overflow-hidden rounded-full bg-sand ring-1 ring-ink/5 transition duration-300 group-hover:-translate-y-1 group-hover:shadow-2xl group-focus-within:ring-2 group-focus-within:ring-coral">
                            @if($tile)
                                {{-- Decorative: the category name sits directly beneath it. --}}
                                <img src="{{ $tile['url'] }}" alt="" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" loading="lazy" aria-hidden="true">
                            @endif
                        </div>
                        <h3 class="mt-6 font-display text-2xl transition group-hover:text-coral">{{ $category->name }}</h3>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    <section id="how-it-works-mobile" class="sm:hidden">
        {{-- ===== PHONE steps (icons only); desktop steps now live in the overlapping band under the hero ===== --}}
        <div class="home-how-it-works px-5 pb-12 pt-8 sm:hidden">
            <div class="text-center"><p class="eyebrow">Simple from start to finish</p><h2 class="mt-1 font-display text-xl">Our magic in 4 simple steps</h2></div>
            <ol class="mt-6 flex items-start justify-between gap-1">
                @foreach([
                    ['cloud-upload', 'Upload their photo'],
                    ['paintbrush', 'We create their artwork'],
                    ['gift', 'Printed with love'],
                    ['truck', 'Delivered to you'],
                ] as [$icon, $label])
                <li class="relative flex flex-1 flex-col items-center text-center">
                    @if(!$loop->first)<span class="absolute right-1/2 top-7 w-full border-t-2 border-dashed border-coral/30" aria-hidden="true"></span>@endif
                    <span class="relative z-10 flex h-14 w-14 items-center justify-center rounded-full border border-coral/20 bg-white text-coral shadow-sm"><i data-lucide="{{ $icon }}" class="h-6 w-6" aria-hidden="true"></i></span>
                    <span class="relative z-10 mt-3 text-[11px] font-semibold leading-tight text-ink">{{ $label }}</span>
                </li>
                @endforeach
            </ol>
        </div>
    </section>
    {{-- ===== PHONE CTA banner (mockup); phone-only, sits above the untouched "Made personal" ===== --}}
    <section class="shell py-8 sm:hidden">
        <a href="{{ route('products.index') }}" class="shine-sweep flex items-center gap-3 overflow-hidden rounded-[2rem] bg-gradient-to-r from-coral to-[#ff7cae] p-4 text-white shadow-lg shadow-coral/20">
            <i data-lucide="sparkles" stroke-width="1.5" class="h-12 w-12 shrink-0 text-white" aria-hidden="true"></i>
            <div class="min-w-0 flex-1">
                <p class="font-display text-base font-semibold leading-tight">Start creating something magical today</p>
                <p class="mt-1 text-xs text-white/85">It only takes a moment to make their day</p>
            </div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-coral shadow"><i data-lucide="arrow-right" class="h-5 w-5" aria-hidden="true"></i></span>
        </a>
    </section>
    {{-- ===== "Two ways to tell their story" — one section for mobile + desktop (warm band, blended illustrations) ===== --}}
    <section class="home-how-it-works px-5 py-8 sm:py-10 lg:py-12">
        <div class="text-center">
            <p class="eyebrow flex items-center justify-center gap-2"><span aria-hidden="true">♡</span> Choose the feeling <span aria-hidden="true">♡</span></p>
            <h2 class="mt-1 font-display text-xl sm:mt-3 sm:text-4xl lg:text-5xl">Two ways to tell their <em class="text-coral not-italic">story.</em></h2>
            <p class="mx-auto mt-3 max-w-xs text-sm leading-6 text-muted sm:mt-5 sm:max-w-2xl sm:text-base sm:leading-7">Each style is created to keep the person—or pet—you love at the heart of the picture.</p>
        </div>
        <div class="mx-auto mt-8 flex max-w-md flex-col divide-y divide-ink/10 sm:mt-12 min-[800px]:max-w-4xl min-[800px]:flex-row min-[800px]:divide-x min-[800px]:divide-y-0">
            @foreach($artworkStyles as $style)
                @if(in_array($style->slug, ['storybook-cartoon', 'hand-drawn']))
                    <article class="flex items-center gap-3 py-5 min-[800px]:flex-1 min-[800px]:gap-6 min-[800px]:px-8 min-[800px]:py-7">
                        <div class="flex-1">
                            <h3 class="whitespace-nowrap font-display text-2xl leading-tight">{{ $style->slug === 'storybook-cartoon' ? 'Cartoon' : $style->name }}</h3>
                            <p class="mt-2 max-w-[9rem] text-sm leading-6 text-muted sm:mt-3 sm:max-w-sm sm:text-base sm:leading-7">{{ $style->description }}</p>
                        </div>
                        <img src="{{ asset('images/artwork-styles/'.$style->slug.'.png') }}" alt="{{ $style->slug === 'storybook-cartoon' ? 'Cartoon' : $style->name }} artwork style example" class="w-28 shrink-0 object-contain mix-blend-multiply sm:w-44">
                    </article>
                @endif
            @endforeach
        </div>
    </section>
    <section class="bg-white/70 pt-6 pb-8 sm:pt-24 sm:pb-24"><div class="shell"><div class="section-heading flex-row items-end justify-between text-left"><div><p class="eyebrow">Made personal</p><h2 class="mt-1! text-xl! sm:mt-4! sm:text-5xl!">Gifts with a story inside.</h2></div><a href="{{ route('products.index') }}" class="hidden font-bold text-coral md:block">See all gifts →</a></div><div class="mt-12 grid grid-cols-2 gap-4 sm:gap-8 lg:grid-cols-4">@foreach($featuredProducts as $product)<x-product-card :product="$product" />@endforeach</div></div></section>
    <section class="shell pt-12 sm:pt-24">
        <div class="rounded-[2.5rem] bg-coral px-8 py-14 text-center text-white sm:px-16">
            <p class="eyebrow text-white/80">Thoughtfully made</p>
            <h2 class="mt-4 font-display text-4xl">Your memory stays the main character.</h2>
            <p class="mx-auto mt-5 max-w-2xl leading-7 text-white/90">You choose the photo, style and final artwork. Nothing goes to print until you’ve seen and approved it.</p>
            <a href="{{ route("products.index") }}" class="mt-8 inline-flex min-h-12 items-center justify-center gap-3 rounded-full bg-white px-7 py-3.5 text-sm font-bold text-coral shadow-lg transition hover:bg-ink hover:text-white">Start creating <span aria-hidden="true">→</span></a>
        </div>
    </section>
    {{-- ===== Trustpilot-style 5-star review carousel (above the footer) ===== --}}
    @php($reviews = [
        ['1.jfif', 'Sarah M.', 'Manchester', 'Absolutely magical — my little girl gasped when she saw her cartoon. The print quality is gorgeous.'],
        ['2.jfif', 'James P.', 'Bristol', 'Ordered a bottle with our dog on it and it looks exactly like him. Fast delivery too!'],
        ['3.jfif', 'Emily R.', 'Leeds', 'The wall print is now the centrepiece of our living room. Everyone asks where we got it.'],
        ['4.jfif', 'Chloe T.', 'Glasgow', 'Such a thoughtful gift for my mum. She cried happy tears. Will definitely order again.'],
        ['5.jfif', 'David H.', 'London', 'From photo to artwork in minutes, and it looked stunning. Couldn’t be happier.'],
    ])
    <section class="pt-14 pb-4">
        <div class="shell text-center">
            <p class="eyebrow">Loved by families</p>
            <div class="mt-3 flex items-center justify-center gap-2">
                <div class="flex gap-0.5">
                    @for($s = 0; $s < 5; $s++)<span class="flex h-6 w-6 items-center justify-center rounded bg-[#00b67a] text-[13px] leading-none text-white" aria-hidden="true">★</span>@endfor
                </div>
                <span class="text-sm font-bold text-ink">Excellent · 4.9/5</span>
            </div>
        </div>
        <div class="mt-7 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-4 sm:px-8 lg:px-12 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($reviews as [$img, $name, $city, $text])
                <article class="flex w-[82%] max-w-sm shrink-0 snap-start flex-col rounded-2xl border border-ink/5 bg-white p-5 shadow-sm sm:w-80">
                    <div class="flex gap-0.5">
                        @for($s = 0; $s < 5; $s++)<span class="flex h-5 w-5 items-center justify-center rounded-sm bg-[#00b67a] text-[11px] leading-none text-white" aria-hidden="true">★</span>@endfor
                    </div>
                    <p class="mt-4 flex-1 text-sm leading-6 text-ink">“{{ $text }}”</p>
                    <div class="mt-5 flex items-center gap-3">
                        <img src="{{ asset('images/reviews/'.$img) }}" alt="{{ $name }}" class="h-10 w-10 rounded-full object-cover" loading="lazy">
                        <div class="leading-tight">
                            <p class="text-sm font-bold text-ink">{{ $name }}</p>
                            <p class="text-xs text-muted">{{ $city }} · Verified purchase</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-layouts.storefront>
