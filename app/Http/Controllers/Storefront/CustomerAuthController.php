<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\CompletePendingCharacterSave;
use App\Domain\Cart\Actions\MergeCustomerCart;
use App\Domain\Orders\Actions\ClaimGuestOrder;
use App\Http\Controllers\Controller;
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

    public function login(Request $request, MergeCustomerCart $merge, ClaimGuestOrder $claim, CompletePendingCharacterSave $pendingCharacter): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean'], 'claim_order' => ['nullable', 'string', 'max:40']]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'is_admin' => false], (bool) ($data['remember'] ?? false))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();
        $merge->handle($request, $request->user());
        $order = ! empty($data['claim_order']) ? $claim->handle($data['claim_order'], $request, $request->user()) : null;
        $characterReturn = $pendingCharacter->handle($request, $request->user());

        return $characterReturn ? redirect()->to($characterReturn) : ($order ? redirect()->route('account.orders.show', $order->number) : redirect()->intended(route('account.index')));
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

    public function register(Request $request, MergeCustomerCart $merge, ClaimGuestOrder $claim, CompletePendingCharacterSave $pendingCharacter): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(8)], 'claim_order' => ['nullable', 'string', 'max:40']]);
        $user = User::query()->create(['email' => strtolower($data['email']), 'password' => Hash::make($data['password']), 'is_admin' => false]);
        Auth::login($user);
        $request->session()->regenerate();
        $merge->handle($request, $user);
        $order = ! empty($data['claim_order']) ? $claim->handle($data['claim_order'], $request, $user) : null;
        $characterReturn = $pendingCharacter->handle($request, $user);

        return $characterReturn ? redirect()->to($characterReturn) : ($order ? redirect()->route('account.orders.show', $order->number) : redirect()->intended(route('account.index')));
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
