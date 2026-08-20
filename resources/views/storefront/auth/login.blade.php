<x-layouts.storefront title="Sign in | Kattie.uk" description="Sign in to see your Kattie orders.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-md">
    <p class="eyebrow">My Account</p><h1 class="mt-3 font-display text-5xl">Welcome back</h1>
    <form method="POST" action="{{ route('login.store') }}" class="mt-10 space-y-5 rounded-[2rem] bg-white p-7 sm:p-9">@csrf
        @if($claimOrder)<input type="hidden" name="claim_order" value="{{ $claimOrder }}">@endif
        <label class="block"><span class="text-sm font-bold">Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3">@error('email')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-sm font-bold">Password</span><input type="password" name="password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3"></label>
        <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="remember" value="1" class="rounded border-rose/40 text-coral"> Remember me</label>
        <button class="button-primary w-full">Sign in</button>
        @include('storefront.auth._google', ['label' => 'Sign in with Google'])
    </form>
    <p class="mt-6 text-center text-sm text-muted">New to Kattie? <a class="font-bold text-coral" href="{{ route('register', array_filter(['claim_order' => $claimOrder])) }}">Create an account</a></p>
</div></section>
</x-layouts.storefront>
