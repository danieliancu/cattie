<x-layouts.storefront title="Sitemap | Kattie.uk" description="Explore Kattie.uk products, collections and customer information." :canonical="route('sitemap')">
    <nav class="shell pt-10 text-sm text-muted" aria-label="Breadcrumb">
        <ol class="flex items-center gap-2"><li><a class="hover:text-coral" href="{{ route('home') }}">Home</a></li><li aria-hidden="true">→</li><li aria-current="page">Sitemap</li></ol>
    </nav>

    <section class="shell py-12 sm:py-16">
        <div class="max-w-2xl"><p class="eyebrow">Find your way</p><h1 class="mt-4 font-display text-5xl sm:text-6xl">Sitemap</h1><p class="mt-5 text-lg leading-8 text-muted">Browse the main areas, collections and personalised gifts available on Kattie.uk.</p></div>

        <div class="mt-12 grid gap-x-12 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
            <section>
                <h2 class="font-display text-2xl"><a class="hover:text-coral" href="{{ route('products.index') }}">Shop</a></h2>
                <ul class="mt-5 space-y-5">
                    @foreach($categories as $category)
                        <li>
                            <a class="font-bold text-ink hover:text-coral" href="{{ $category->url() }}">{{ $category->name }}</a>
                            @if($category->children->isNotEmpty())
                                <ul class="mt-2 space-y-2 border-l border-rose/30 pl-4 text-sm text-muted">
                                    @foreach($category->children as $child)<li><a class="hover:text-coral" href="{{ $child->url() }}">{{ $child->name }}</a></li>@endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section>
                <h2 class="font-display text-2xl">All personalised gifts</h2>
                <ul class="mt-5 space-y-3 text-sm text-muted">
                    @foreach($products as $product)<li><a class="hover:text-coral" href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a></li>@endforeach
                </ul>
            </section>

            <section>
                <h2 class="font-display text-2xl">Customer information</h2>
                <ul class="mt-5 space-y-3 text-sm text-muted">
                    <li><a class="hover:text-coral" href="{{ route('information.faq') }}">FAQ</a></li>
                    <li><a class="hover:text-coral" href="{{ route('information.delivery') }}">Delivery &amp; Shipping</a></li>
                    <li><a class="hover:text-coral" href="{{ route('information.returns') }}">Returns Policy</a></li>
                    <li><a class="hover:text-coral" href="{{ route('information.privacy') }}">Privacy Policy</a></li>
                    <li><a class="hover:text-coral" href="{{ route('information.cookies') }}">Manage Cookies</a></li>
                    <li><a class="hover:text-coral" href="{{ route('information.payments') }}">Payment Methods</a></li>
                    <li><a class="hover:text-coral" href="{{ route('information.terms') }}">Terms and Conditions</a></li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.storefront>
