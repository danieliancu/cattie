<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Auth\Actions\SendEmailVerificationCode;
use App\Domain\Cart\Actions\MergeCustomerCart;
use App\Domain\Orders\Actions\ClaimGuestOrder;
use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\Order;
use App\Models\User;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CustomerAuthController extends Controller
{
    public function loginForm(Request $request): View
    {
        return view('storefront.auth.login', ['claimOrder' => $this->claimNumber($request)]);
    }

    public function login(Request $request, MergeCustomerCart $merge, ClaimGuestOrder $claim, SendEmailVerificationCode $sendCode): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean'], 'claim_order' => ['nullable', 'string', 'max:40']]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'is_admin' => false], (bool) ($data['remember'] ?? false))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();
        $user = $request->user();

        // An account that never finished email verification is sent back to the code screen.
        if (! $user->hasVerifiedEmail()) {
            $sendCode->handle($user);
            $request->session()->put('registration_claim_order', $data['claim_order'] ?? null);

            return redirect()->route('verification.notice');
        }

        $merge->handle($request, $user);
        $order = ! empty($data['claim_order']) ? $claim->handle($data['claim_order'], $request, $user) : null;

        return $order ? redirect()->route('account.orders.show', $order->number) : redirect()->intended(route('account.index'));
    }

    public function registerForm(Request $request, GuestContext $guest): View
    {
        $claimOrder = $this->claimNumber($request);
        $email = null;
        if ($claimOrder) {
            $order = Order::query()->where('number', $claimOrder)->first();
            $email = $order && $guest->owns($order->access_token_hash, $request) ? $order->email : null;
        }

        return view('storefront.auth.register', compact('claimOrder', 'email'));
    }

    public function register(Request $request, SendEmailVerificationCode $sendCode): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(8)], 'claim_order' => ['nullable', 'string', 'max:40']]);
        $user = User::query()->create(['email' => strtolower($data['email']), 'password' => Hash::make($data['password']), 'is_admin' => false]);
        Auth::login($user);
        $request->session()->regenerate();

        // The account exists but stays unverified until the emailed code is confirmed.
        $sendCode->handle($user);
        $request->session()->put('registration_claim_order', $data['claim_order'] ?? null);

        return redirect()->route('verification.notice');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        return view('storefront.auth.verify', ['email' => $user->email]);
    }

    public function verify(Request $request, MergeCustomerCart $merge, ClaimGuestOrder $claim): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $code = preg_replace('/\D/', '', $data['code']);

        $record = EmailVerificationCode::query()->where('user_id', $user->id)->first();
        if (! $record || $record->isExpired()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Please request a new one.']);
        }
        if ($record->attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please request a new code.']);
        }
        if ($code === '' || ! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            throw ValidationException::withMessages(['code' => 'That code is not correct.']);
        }

        $user->markEmailAsVerified();
        $record->delete();

        $merge->handle($request, $user);
        $claimOrder = $request->session()->pull('registration_claim_order');
        $order = ! empty($claimOrder) ? $claim->handle($claimOrder, $request, $user) : null;

        return $order ? redirect()->route('account.orders.show', $order->number) : redirect()->intended(route('account.index'));
    }

    public function resend(Request $request, SendEmailVerificationCode $sendCode): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        $sendCode->handle($user);

        return back()->with('status', 'We have sent you a new code.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function claimNumber(Request $request): ?string
    {
        $value = $request->query('claim_order', $request->old('claim_order'));

        return is_string($value) && strlen($value) <= 40 ? $value : null;
    }
}
