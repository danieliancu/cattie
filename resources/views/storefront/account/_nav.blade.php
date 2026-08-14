<nav aria-label="Account navigation" class="mt-7 flex flex-wrap items-center gap-3 text-sm font-bold">
    <a href="{{ route('account.index') }}" class="rounded-full px-4 py-2 {{ request()->routeIs('account.index') ? 'bg-coral text-white' : 'bg-white' }}">Overview</a>
    <a href="{{ route('account.orders.index') }}" class="rounded-full px-4 py-2 {{ request()->routeIs('account.orders.*') ? 'bg-coral text-white' : 'bg-white' }}">My Orders</a>
    <a href="{{ route('account.characters.index') }}" class="rounded-full px-4 py-2 {{ request()->routeIs('account.characters.*') ? 'bg-coral text-white' : 'bg-white' }}">My Characters</a>
    <form method="POST" action="{{ route('logout', [], false) }}">@csrf<button type="submit" class="rounded-full px-4 py-2 text-muted underline">Sign out</button></form>
</nav>
