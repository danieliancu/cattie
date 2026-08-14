<x-layouts.storefront title="My Characters | Cattie.uk" description="Your saved Cattie characters.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-5xl">
    <p class="eyebrow">My Account</p><h1 class="mt-3 font-display text-5xl">My Characters</h1>
    <p class="mt-4 text-muted">Reuse your favourite characters on compatible products without uploading another photo.</p>
    @include('storefront.account._nav')
    @if(session('status'))<p class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm font-bold text-emerald-700">{{ session('status') }}</p>@endif
    @if($characters->isEmpty())
        <div class="mt-10 rounded-[2rem] bg-white p-8 text-center"><h2 class="font-display text-3xl">No saved characters yet</h2><p class="mt-3 text-muted">Create an illustration, then choose Save character.</p><a href="{{ route('products.index') }}" class="button-primary mt-6">Choose a product</a></div>
    @else
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($characters as $character)<article class="rounded-[2rem] bg-white p-5 shadow-sm"><div class="flex h-64 items-center justify-center rounded-2xl bg-sand/50 p-4"><img src="{{ route('account.characters.preview',$character) }}" alt="{{ $character->name }}" class="h-full w-full object-contain"></div><h2 class="mt-5 font-display text-2xl">{{ $character->name }}</h2><p class="mt-1 text-sm text-muted">{{ $character->artworkStyle->slug === 'storybook-cartoon' ? 'Cartoon' : $character->artworkStyle->name }} · Saved {{ $character->created_at->format('j M Y') }}</p><form method="POST" action="{{ route('account.characters.destroy',$character) }}" class="mt-5" onsubmit="return confirm('Delete this character from your library?')">@csrf @method('DELETE')<button class="text-sm font-bold text-coral underline">Delete character</button></form></article>@endforeach
        </div>
    @endif
</div></section>
</x-layouts.storefront>
