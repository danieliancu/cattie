<!DOCTYPE html>
<html lang="en-GB" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cattie.uk — Personalised gifts made magical' }}</title>
    <meta name="description" content="{{ $description ?? 'Turn a favourite photo into heartfelt personalised artwork and gifts.' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|fraunces:500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream text-ink antialiased">
<a href="#main-content" class="skip-link">Skip to content</a>
<header class="border-b border-rose/20 bg-cream/95 backdrop-blur">
    <div class="shell flex h-20 items-center justify-between">
        <a href="{{ route('home') }}" class="brand" aria-label="Cattie.uk home">Cattie<span>.</span>uk</a>
        <nav aria-label="Main navigation" class="flex items-center gap-7 text-sm font-semibold">
            <a class="nav-link {{ request()->routeIs('products.*') ? 'text-coral' : '' }}" href="{{ route('products.index') }}">Shop</a>
            <a class="nav-link hidden sm:inline" href="{{ route('home') }}#how-it-works">How it works</a>
            <a class="nav-link {{ request()->routeIs('cart.*') ? 'text-coral' : '' }}" href="{{ route('cart.index') }}">Basket</a>
        </nav>
    </div>
</header>
<main id="main-content">{{ $slot }}</main>
<footer class="mt-24 border-t border-rose/20 bg-white/55">
    <div class="shell grid gap-10 py-12 md:grid-cols-2">
        <div><div class="brand">Cattie<span>.</span>uk</div><p class="mt-3 max-w-md text-sm leading-6 text-muted">Thoughtful personalised gifts, created from the photographs and little moments you already treasure.</p></div>
        <div class="md:text-right"><p class="font-display text-lg">Made for meaningful moments.</p><p class="mt-3 text-sm text-muted">Designed in the UK · Photos handled with care</p></div>
    </div>
</footer>
</body>
</html>
