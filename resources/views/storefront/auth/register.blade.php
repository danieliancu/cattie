<x-layouts.storefront title="Create your account | Kattie.uk" description="Create a Kattie account to keep track of your orders.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-md">
    <p class="eyebrow">My Account</p><h1 class="mt-3 font-display text-5xl">Create your account</h1>
    <form method="POST" action="{{ route('register.store') }}" class="mt-10 space-y-5 rounded-[2rem] bg-white p-7 sm:p-9">@csrf
        @if($claimOrder)<input type="hidden" name="claim_order" value="{{ $claimOrder }}">@endif
        <label class="block"><span class="text-sm font-bold">Email</span><input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3">@error('email')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-sm font-bold">Password</span><input type="password" name="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3">@error('password')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-sm font-bold">Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3"></label>
        <button class="button-primary w-full">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Already have an account? <a class="font-bold text-coral" href="{{ route('login', array_filter(['claim_order' => $claimOrder])) }}">Sign in</a></p>
</div></section>
</x-layouts.storefront>
