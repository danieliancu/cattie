<!DOCTYPE html>
<html lang="en-GB" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Kattie.uk — Personalised gifts made magical' }}</title>
    <meta name="description" content="{{ $description ?? 'Turn a favourite photo into heartfelt personalised artwork and gifts.' }}">
    @isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
    @unless(($robots ?? null) === 'noindex,nofollow')<link rel="canonical" href="{{ $canonical ?? url()->current() }}">@endunless
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|fraunces:500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-x-clip bg-white text-ink antialiased">
<a href="#main-content" class="skip-link">Skip to content</a>
<header class="sticky top-0 z-40 border-b border-rose/20 bg-cream/95 backdrop-blur" x-data="{mobileMenu:false,mobileAccountOpen:false,mobileOpenMenu:null,desktopOpenMenu:null,searchOpen:false,searchQuery:@js((string) request('q', '')),searchProducts:@js($searchProducts),filteredProducts(){const query=this.searchQuery.trim().toLowerCase();return query===''?this.searchProducts:this.searchProducts.filter(product=>product.name.toLowerCase().includes(query))}}">
    <div class="shell py-4 lg:grid lg:grid-cols-[auto_minmax(240px,1fr)_auto] lg:items-center lg:gap-8">
        <div class="flex items-center justify-between pl-[50px]">
            <a href="{{ route('home') }}" class="brand relative ml-8 inline-flex lg:ml-0" aria-label="Kattie.uk home"><img src="{{ asset('images/icon.gif') }}" class="absolute -left-20 -bottom-5 z-[1] h-auto w-[85px] lg:-bottom-[25px]" alt="" aria-hidden="true">Kattie<span>.</span>uk<span class="absolute left-0 top-[calc(100%-6px)] whitespace-nowrap pl-[5px] font-sans text-[8px] font-light tracking-[0.8px] text-muted">LITTLE FACES. BIG LOVE</span></a>
            <div class="flex items-center gap-4 lg:hidden">
                <div class="relative" @click.outside="mobileAccountOpen=false">
                    <button type="button" class="cursor-pointer rounded-full p-1 {{ request()->routeIs('account.*') ? 'text-coral' : 'text-ink' }}" @click="mobileAccountOpen=!mobileAccountOpen" :aria-expanded="mobileAccountOpen" aria-label="My Account"><i data-lucide="user-round" class="h-5 w-5" aria-hidden="true"></i></button>
                    <div x-show="mobileAccountOpen" x-cloak class="absolute right-0 top-full z-50 mt-3 w-48 rounded-2xl border border-rose/25 bg-white p-2 text-sm font-semibold shadow-2xl">
                        @auth<a href="{{ route('account.index') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">My Account</a><a href="{{ route('account.orders.index') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">My Orders</a><form method="POST" action="{{ route('logout', [], false) }}">@csrf<button type="submit" class="block w-full rounded-xl px-4 py-3 text-left hover:bg-blush/40 hover:text-coral">Sign out</button></form>
                        @else<a href="{{ route('login') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">Sign in</a><a href="{{ route('register') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">Create an account</a>@endauth
                    </div>
                </div>
                <a href="{{ route('cart.index') }}" class="relative rounded-full p-1 {{ request()->routeIs('cart.*') ? 'text-coral' : 'text-ink' }}" aria-label="Basket"><i data-lucide="shopping-cart" class="h-5 w-5" aria-hidden="true"></i>@if($basketItemCount > 0)<span class="absolute -right-2 -top-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-coral px-1 text-[10px] font-bold leading-none text-white" aria-label="{{ $basketItemCount }} items in basket">{{ $basketItemCount }}</span>@endif</a>
                <button type="button" class="rounded-full p-1 text-ink" @click="mobileMenu=!mobileMenu" :aria-expanded="mobileMenu" aria-controls="mobile-navigation" aria-label="Open menu"><i x-show="!mobileMenu" data-lucide="menu" class="h-6 w-6" aria-hidden="true"></i><i x-show="mobileMenu" data-lucide="x" class="h-6 w-6" aria-hidden="true"></i></button>
            </div>
        </div>

        <form action="{{ route('products.index') }}" method="GET" role="search" class="relative mt-4 lg:mt-0 lg:justify-self-center lg:w-full lg:max-w-xl" @click.outside="searchOpen=false">
            <label for="site-search" class="sr-only">Search products</label>
            <input id="site-search" name="q" type="search" x-model="searchQuery" @focus="searchOpen=true" @click="searchOpen=true" @keydown.escape="searchOpen=false" autocomplete="off" placeholder="Search gifts" class="w-full rounded-full border border-rose/40 bg-white py-2.5 pl-11 pr-4 text-sm" aria-controls="product-search-results" :aria-expanded="searchOpen">
            <button type="submit" class="absolute inset-y-0 left-0 flex w-11 items-center justify-center text-muted" aria-label="Search"><i data-lucide="search" class="h-5 w-5" aria-hidden="true"></i></button>
            <div id="product-search-results" x-show="searchOpen" x-cloak class="absolute inset-x-0 top-full z-50 mt-2 max-h-80 overflow-y-auto rounded-2xl border border-rose/25 bg-white p-2 shadow-2xl">
                <template x-for="product in filteredProducts()" :key="product.url"><a :href="product.url" class="block rounded-xl px-4 py-3 text-sm font-semibold text-ink transition hover:bg-blush/40 hover:text-coral" x-text="product.name"></a></template>
                <p x-show="filteredProducts().length===0" class="px-4 py-5 text-center text-sm text-muted">No gifts found.</p>
            </div>
        </form>

        <nav aria-label="Main navigation" class="hidden items-center gap-6 whitespace-nowrap text-sm font-semibold lg:flex">
            <div class="relative" @click.outside="desktopOpenMenu=null">
                <a class="nav-link inline-flex cursor-pointer items-center gap-1 {{ request()->routeIs('products.*', 'catalogue.*') ? 'text-coral' : '' }}" href="{{ route('products.index') }}" @mouseenter="desktopOpenMenu='shop'"><span>Shop</span><i data-lucide="chevron-down" class="h-3.5 w-3.5 cursor-pointer" aria-hidden="true" @click.prevent="desktopOpenMenu = desktopOpenMenu==='shop' ? null : 'shop'"></i></a>
                <div x-show="desktopOpenMenu==='shop'" x-cloak @mouseleave="desktopOpenMenu=null" class="absolute left-1/2 top-full z-50 mt-3 w-[min(64rem,calc(100vw-3rem))] -translate-x-1/2 rounded-2xl border border-rose/25 bg-white p-6 shadow-2xl">
                    @if($navCategories->isNotEmpty())
                        <div class="grid gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach($navCategories as $category)
                                <div>
                                    <a href="{{ $category->url() }}" class="block font-display text-lg hover:text-coral">{{ $category->name }}</a>
                                    @if($category->children->isNotEmpty())
                                        <ul class="mt-3 space-y-1.5">
                                            @foreach($category->children as $child)<li><a href="{{ $child->url() }}" class="block text-[13px] font-normal leading-5 text-muted hover:text-coral">{{ $child->name }}</a></li>@endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 border-t border-rose/25 pt-4"><a href="{{ route('products.index') }}" class="font-bold text-coral hover:text-ink">Shop all →</a></div>
                    @else
                        <a href="{{ route('products.index') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">Shop all</a>
                    @endif
                </div>
            </div>
            <a class="nav-link" href="{{ route('home') }}#how-it-works">How it works</a>
            <div class="relative" @click.outside="desktopOpenMenu=null">
                <a class="nav-link inline-flex cursor-pointer items-center gap-1" href="{{ route('order-support.create') }}" @mouseenter="desktopOpenMenu='order-support'"><span>Order Support</span><i data-lucide="chevron-down" class="h-3.5 w-3.5 cursor-pointer" aria-hidden="true" @click.prevent="desktopOpenMenu = desktopOpenMenu==='order-support' ? null : 'order-support'"></i></a>
                <div x-show="desktopOpenMenu==='order-support'" x-cloak @mouseleave="desktopOpenMenu=null" class="absolute left-0 top-full z-50 mt-3 w-48 rounded-2xl border border-rose/25 bg-white p-2 shadow-2xl">
                    <a href="#" class="block rounded-xl px-4 py-3 text-ink hover:bg-blush/40 hover:text-coral">Track Order</a><a href="{{ route('order-support.create') }}" class="block rounded-xl px-4 py-3 text-ink hover:bg-blush/40 hover:text-coral">Something wrong?</a>
                </div>
            </div>
            <div class="relative" @click.outside="desktopOpenMenu=null">
                <a class="nav-link inline-flex cursor-pointer items-center gap-1.5 {{ request()->routeIs('account.*') ? 'text-coral' : '' }}" href="@auth{{ route('account.index') }}@else{{ route('login') }}@endauth" @mouseenter="desktopOpenMenu='account'"><i data-lucide="user-round" class="h-4 w-4" aria-hidden="true"></i><span>@auth My Account @else Sign in @endauth</span><i data-lucide="chevron-down" class="h-3.5 w-3.5 cursor-pointer" aria-hidden="true" @click.prevent="desktopOpenMenu = desktopOpenMenu==='account' ? null : 'account'"></i></a>
                <div x-show="desktopOpenMenu==='account'" x-cloak @mouseleave="desktopOpenMenu=null" class="absolute left-0 top-full z-50 mt-3 w-48 rounded-2xl border border-rose/25 bg-white p-2 shadow-2xl">
                    @auth<a href="{{ route('account.orders.index') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">My Orders</a><a href="{{ route('account.details') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">My Details</a><form method="POST" action="{{ route('logout', [], false) }}">@csrf<button type="submit" class="block w-full rounded-xl px-4 py-3 text-left hover:bg-blush/40 hover:text-coral">Sign out</button></form>
                    @else<a href="{{ route('login') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">Sign in</a><a href="{{ route('register') }}" class="block rounded-xl px-4 py-3 hover:bg-blush/40 hover:text-coral">Create an account</a>@endauth
                </div>
            </div>
            <a class="nav-link inline-flex items-center gap-2 {{ request()->routeIs('cart.*') ? 'text-coral' : '' }}" href="{{ route('cart.index') }}"><i data-lucide="shopping-cart" class="h-4 w-4" aria-hidden="true"></i>Basket @if($basketItemCount > 0)<span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-coral px-1 text-[11px] font-bold leading-none text-white" aria-label="{{ $basketItemCount }} items in basket">{{ $basketItemCount }}</span>@endif</a>
        </nav>

        <nav id="mobile-navigation" x-show="mobileMenu" x-cloak aria-label="Mobile navigation" class="mt-4 border-t border-rose/20 pt-3 text-sm font-semibold lg:hidden">
            <div class="flex items-center justify-between">
                <a class="nav-link block flex-1" href="{{ route('products.index') }}">Shop</a>
                <button type="button" class="p-2" @click="mobileOpenMenu = mobileOpenMenu==='shop' ? null : 'shop'" :aria-expanded="mobileOpenMenu==='shop'" aria-controls="mobile-shop-categories" aria-label="Toggle shop categories"><i data-lucide="chevron-down" class="h-4 w-4 transition" :class="mobileOpenMenu==='shop' ? 'rotate-180' : ''" aria-hidden="true"></i></button>
            </div>
            <div id="mobile-shop-categories" x-show="mobileOpenMenu==='shop'" x-cloak class="ml-3 border-l border-rose/20 pl-3" x-data="{openCategory:null}">
                @foreach($navCategories as $category)
                    <div class="flex items-center justify-between">
                        <a class="nav-link block flex-1 text-sm" href="{{ $category->url() }}">{{ $category->name }}</a>
                        @if($category->children->isNotEmpty())
                            <button type="button" class="p-2" @click="openCategory = openCategory==={{ $loop->index }} ? null : {{ $loop->index }}" :aria-expanded="openCategory==={{ $loop->index }}" aria-controls="mobile-subcategories-{{ $category->slug }}" aria-label="Toggle {{ $category->name }} subcategories"><i data-lucide="chevron-down" class="h-4 w-4 transition" :class="openCategory==={{ $loop->index }} ? 'rotate-180' : ''" aria-hidden="true"></i></button>
                        @endif
                    </div>
                    @if($category->children->isNotEmpty())
                        <div id="mobile-subcategories-{{ $category->slug }}" x-show="openCategory==={{ $loop->index }}" x-cloak class="ml-3 border-l border-rose/20 pl-3">
                            @foreach($category->children as $child)<a href="{{ $child->url() }}" class="nav-link block text-sm font-normal">{{ $child->name }}</a>@endforeach
                        </div>
                    @endif
                @endforeach
                <a href="{{ route('products.index') }}" class="nav-link block text-sm font-normal">Shop all</a>
            </div>
            <a class="nav-link block" href="{{ route('home') }}#how-it-works">How it works</a>
            <div class="flex items-center justify-between">
                <a class="nav-link block flex-1 py-2" href="{{ route('order-support.create') }}">Order Support</a>
                <button type="button" class="p-2" @click="mobileOpenMenu = mobileOpenMenu==='order-support' ? null : 'order-support'" :aria-expanded="mobileOpenMenu==='order-support'" aria-controls="mobile-order-support-links" aria-label="Toggle order support links"><i data-lucide="chevron-down" class="h-4 w-4 transition" :class="mobileOpenMenu==='order-support' ? 'rotate-180' : ''" aria-hidden="true"></i></button>
            </div>
            <div id="mobile-order-support-links" x-show="mobileOpenMenu==='order-support'" x-cloak class="ml-3 border-l border-rose/20 pl-3">
                <a class="nav-link block text-sm font-normal" href="#">Track Order</a>
                <a class="nav-link block text-sm font-normal" href="{{ route('order-support.create') }}">Something wrong?</a>
            </div>
        </nav>
    </div>
</header>
@if($errors->any())
<div x-data="{open:true}" x-show="open" x-cloak @keydown.escape.window="open=false" class="fixed inset-0 z-[100] flex items-center justify-center bg-ink/55 p-4" role="presentation">
    <div @click.outside="open=false" class="relative w-full max-w-lg rounded-[2rem] bg-white p-7 shadow-2xl sm:p-9" role="dialog" aria-modal="true" aria-labelledby="validation-error-title">
        <button type="button" @click="open=false" class="absolute right-5 top-5 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full text-2xl text-muted hover:bg-rose/20 hover:text-ink" aria-label="Close error message">×</button>
        <p class="eyebrow text-red-700">Please check your details</p>
        <h2 id="validation-error-title" class="mt-3 pr-10 font-display text-3xl">We couldn’t continue</h2>
        <ul class="mt-5 space-y-2 text-sm leading-6 text-red-800">@foreach($errors->all() as $error)<li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $error }}</span></li>@endforeach</ul>
        @if($errors->has('photo'))
            <div class="mt-6 rounded-2xl border border-rose/30 bg-cream p-4">
                <h3 class="font-bold">Photo requirements</h3>
                <ul class="mt-2 space-y-1 text-sm leading-6 text-muted">
                    <li>JPEG, PNG or WebP format</li>
                    <li>Maximum file size: {{ config('artwork.upload.max_kb') / 1024 }} MB</li>
                    <li>Width and height: {{ config('artwork.upload.min_dimension') }}–{{ config('artwork.upload.max_dimension') }} px</li>
                    <li>For best results, use a clear portrait with the full person visible.</li>
                </ul>
            </div>
        @endif
        @if($errors->has('pricing_hash'))<a class="mt-5 inline-block font-bold text-coral underline" href="{{ route('cart.index') }}">Review basket</a>@endif
        <button type="button" @click="open=false" class="button-primary mt-6 w-full cursor-pointer">Try again</button>
    </div>
</div>
@endif
<main id="main-content">{{ $slot }}</main>
<footer class="mt-24 border-t border-rose/20 bg-cream/95">
    <div class="shell grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-[1.9fr_1fr_1fr_1fr_1fr] lg:gap-10">
        <div class="lg:pr-12 xl:pr-20">
            <div class="brand relative ml-11 inline-flex">Kattie<span>.</span>uk<img src="{{ asset('images/icon.gif') }}" class="absolute h-auto w-[65px]" style="left:-60px;bottom:-5px;" alt="" aria-hidden="true"><span class="absolute left-0 top-[calc(100%-6px)] whitespace-nowrap pl-[5px] font-sans text-[8px] font-light tracking-[0.8px] text-muted">LITTLE FACES. BIG LOVE</span></div>
            <p class="mt-5 max-w-md text-sm leading-6 text-muted">Thoughtful personalised gifts, created from the photographs and little moments you already treasure.</p>
            <p class="mt-7 max-w-md text-sm leading-6 text-ink">Get unique gift ideas and so much more delivered right to your inbox.</p>
            <form class="mt-4 flex max-w-md gap-2" @submit.prevent>
                <label for="footer-email" class="sr-only">Email address</label>
                <input id="footer-email" type="email" placeholder="Email address" class="min-w-0 flex-1 rounded-full border border-rose/40 bg-white px-4 py-2.5 text-sm">
                <button type="submit" class="cursor-pointer rounded-full bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:bg-ink">Subscribe</button>
            </form>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[.16em] text-ink">Shop</h2>
            <ul class="mt-5 space-y-3 text-sm leading-6 text-muted">
                @foreach($navCategories as $category)<li><a class="hover:text-coral" href="{{ $category->url() }}">{{ $category->name }}</a></li>@endforeach
                <li><a class="hover:text-coral" href="{{ route('products.index') }}">Shop all</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[.16em] text-ink">About Us</h2>
            <ul class="mt-5 space-y-3 text-sm leading-6 text-muted"><li>About Kattie</li><li>Contact us</li><li><a class="hover:text-coral" href="{{ route('information.terms') }}">Terms and Conditions</a></li><li>Blog</li><li><a class="hover:text-coral" href="{{ route('sitemap') }}">Sitemap</a></li></ul>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[.16em] text-ink">Customer Service</h2>
            <ul class="mt-5 space-y-3 text-sm leading-6 text-muted"><li><a class="hover:text-coral" href="{{ route('information.faq') }}">FAQ</a></li><li><a class="hover:text-coral" href="{{ route('information.delivery') }}">Delivery &amp; Shipping</a></li><li><a class="hover:text-coral" href="{{ route('information.returns') }}">Returns Policy</a></li><li><a class="hover:text-coral" href="{{ route('information.privacy') }}">Privacy Policy</a></li><li><a class="hover:text-coral" href="{{ route('information.payments') }}">Payment Methods</a></li><li><a class="hover:text-coral" href="{{ route('order-support.create') }}">Order Support</a></li></ul>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[.16em] text-ink">Contact Us</h2>
            <ul class="mt-5 space-y-3 text-sm leading-6 text-muted"><li>Messenger</li><li>WhatsApp</li><li>Email:support@kattie.uk</li><li>24/7 Customer Support</li></ul>
        </div>
    </div>
    <div class="border-t border-rose/20">
        <div class="shell flex flex-col items-center justify-center gap-3 py-4 text-[10px] text-muted sm:flex-row sm:gap-12 sm:text-xs lg:gap-20">
            <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap"><svg viewBox="0 0 60 30" class="h-3.5 w-7" role="img" aria-label="United Kingdom flag"><clipPath id="uk-flag-clip"><rect width="60" height="30" rx="1"/></clipPath><g clip-path="url(#uk-flag-clip)"><path fill="#012169" d="M0 0h60v30H0z"/><path stroke="#fff" stroke-width="6" d="m0 0 60 30m0-30L0 30"/><path stroke="#C8102E" stroke-width="2" d="m0 0 60 30m0-30L0 30"/><path stroke="#fff" stroke-width="10" d="M30 0v30M0 15h60"/><path stroke="#C8102E" stroke-width="6" d="M30 0v30M0 15h60"/></g></svg> United Kingdom</span>
            <a class="shrink-0 whitespace-nowrap hover:text-coral" href="{{ route('information.cookies') }}">Manage Cookies</a>
            <span class="shrink-0 whitespace-nowrap">Copyright © 2026, KATTIE All Rights Reserved</span>
        </div>
    </div>
</footer>
</body>
</html>
