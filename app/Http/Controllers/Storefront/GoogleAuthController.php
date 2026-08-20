<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Actions\MergeCustomerCart;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

final class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, MergeCustomerCart $merge): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in failed. Please try again or use your email.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        if ($email === '') {
            return redirect()->route('login')->withErrors(['email' => 'Google did not share an email address for this account.']);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if ($user) {
            // Admin accounts are managed separately and must not be claimable via social login.
            if ($user->is_admin) {
                return redirect()->route('login')->withErrors(['email' => 'Please sign in with your email and password.']);
            }
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'name' => $user->name ?: $googleUser->getName(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::query()->create([
                'email' => $email,
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'password' => null,
                'is_admin' => false,
            ]);
            // email_verified_at is guarded, so set it through the model helper.
            $user->markEmailAsVerified();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $merge->handle($request, $user);

        return redirect()->intended(route('account.index'));
    }
}
