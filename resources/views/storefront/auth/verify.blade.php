<x-layouts.storefront title="Confirm your email | Kattie.uk" description="Enter the code we emailed to finish creating your account.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-md">
    <p class="eyebrow">One last step</p>
    <h1 class="mt-3 font-display text-5xl">Check your inbox</h1>
    <p class="mt-4 text-muted">We emailed a 6-digit code to <span class="font-bold text-ink">{{ $email }}</span>. Enter it below to confirm your email and finish creating your account.</p>

    @if(session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('register.verify.store') }}" class="mt-8 space-y-5 rounded-[2rem] bg-white p-7 sm:p-9">@csrf
        <label class="block">
            <span class="text-sm font-bold">Verification code</span>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required autofocus
                   class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3 text-center text-3xl font-bold tracking-[0.5em]"
                   placeholder="------">
            @error('code')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>
        <button class="button-primary w-full">Confirm email</button>
    </form>

    <form method="POST" action="{{ route('register.verify.resend') }}" class="mt-6 text-center">@csrf
        <p class="text-sm text-muted">Didn't get it? Check spam, or
            <button type="submit" class="font-bold text-coral underline">send a new code</button>.
        </p>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-2 text-center">@csrf
        <button type="submit" class="text-sm text-muted underline">Use a different email</button>
    </form>
</div></section>
</x-layouts.storefront>
