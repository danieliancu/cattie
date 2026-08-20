<x-layouts.storefront title="My Account | Kattie.uk" description="Manage your Kattie account.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-5xl">
    <p class="eyebrow">My Account</p><h1 class="mt-3 font-display text-5xl">Hello</h1><p class="mt-4 text-muted">{{ auth()->user()->email }}</p>
    @include('storefront.account._nav')

    {{-- Your name --}}
    <div class="mt-10 rounded-[2rem] bg-white p-7 sm:p-9" x-data="{
        status: 'idle',
        async save() {
            this.status = 'saving';
            try {
                const response = await fetch(@js(route('account.details.update')), {
                    method: 'PATCH',
                    headers: {'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json'},
                    body: JSON.stringify({first_name: this.$refs.firstName.value, last_name: this.$refs.lastName.value}),
                });
                this.status = response.ok ? 'saved' : 'error';
            } catch (e) { this.status = 'error'; }
        }
    }">
        <h2 class="font-display text-3xl">Your name</h2>
        <p class="mt-2 text-sm text-muted">Also editable under <a class="font-bold text-coral" href="{{ route('account.details') }}">My Details</a>.</p>
        <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <label class="block"><span class="text-sm font-bold">First name</span><input x-ref="firstName" type="text" maxlength="100" value="{{ $firstName }}" autocomplete="given-name" class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3"></label>
            <label class="block"><span class="text-sm font-bold">Last name</span><input x-ref="lastName" type="text" maxlength="100" value="{{ $lastName }}" autocomplete="family-name" class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3"></label>
        </div>
        <div class="mt-6 flex items-center gap-4">
            <button type="button" class="button-primary" @click="save()">Save name</button>
            <p class="text-sm font-bold" x-cloak>
                <span x-show="status === 'saving'" class="text-muted">Saving…</span>
                <span x-show="status === 'saved'" class="text-emerald-700">✓ Saved</span>
                <span x-show="status === 'error'" class="text-red-700">Couldn't save — try again</span>
            </p>
        </div>
    </div>

    {{-- Password --}}
    <div class="mt-6 rounded-[2rem] bg-white p-7 sm:p-9">
        <h2 class="font-display text-3xl">{{ $hasPassword ? 'Change password' : 'Set a password' }}</h2>
        @unless($hasPassword)<p class="mt-2 text-sm text-muted">You signed up with Google. Set a password to also sign in with your email.</p>@endunless
        @if(session('password_status'))<p class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('password_status') }}</p>@endif
        <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 max-w-md space-y-5">@csrf @method('PUT')
            @if($hasPassword)
            <label class="block"><span class="text-sm font-bold">Current password</span><input type="password" name="current_password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3">@error('current_password')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
            @endif
            <label class="block"><span class="text-sm font-bold">New password</span><input type="password" name="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3">@error('password')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="text-sm font-bold">Confirm new password</span><input type="password" name="password_confirmation" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-rose/30 bg-cream px-4 py-3"></label>
            <button class="button-primary">{{ $hasPassword ? 'Update password' : 'Set password' }}</button>
        </form>
    </div>

    {{-- Delete account --}}
    <div class="mt-6 rounded-[2rem] border border-red-200 bg-red-50/60 p-7 sm:p-9" x-data="{ confirming: false }">
        <h2 class="font-display text-3xl text-red-700">Delete account</h2>
        <p class="mt-2 text-sm text-red-700/80">This permanently deletes your account and sign-in details. Your past orders are kept for our records but are no longer linked to you. This cannot be undone.</p>
        @error('confirm_password')<p class="mt-4 rounded-xl bg-red-100 p-3 text-sm font-medium text-red-700">{{ $message }}</p>@enderror

        <button type="button" x-show="!confirming" @click="confirming = true" class="mt-6 rounded-full border-2 border-red-300 bg-white px-6 py-3 font-bold text-red-700 transition hover:bg-red-100">Delete my account</button>

        <form x-show="confirming" x-cloak method="POST" action="{{ route('account.destroy') }}" class="mt-6 max-w-md space-y-5">@csrf @method('DELETE')
            @if($hasPassword)
            <label class="block"><span class="text-sm font-bold text-red-700">Enter your password to confirm</span><input type="password" name="confirm_password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-red-300 bg-white px-4 py-3"></label>
            @else
            <p class="text-sm font-bold text-red-700">Are you sure? This is permanent.</p>
            @endif
            <div class="flex flex-wrap items-center gap-3">
                <button class="rounded-full bg-red-600 px-6 py-3 font-bold text-white transition hover:bg-red-700">Yes, delete my account</button>
                <button type="button" @click="confirming = false" class="font-bold text-muted underline">Cancel</button>
            </div>
        </form>
    </div>
</div></section>
</x-layouts.storefront>
