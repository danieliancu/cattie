<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class AccountSecurityController extends Controller
{
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $hasPassword = $user->password !== null;

        $rules = ['password' => ['required', 'confirmed', Password::min(8)]];
        if ($hasPassword) {
            $rules['current_password'] = ['required', 'string'];
        }
        $data = $request->validate($rules);

        if ($hasPassword && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is not correct.']);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('password_status', $hasPassword ? 'Your password has been updated.' : 'Your password has been set.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Accounts with a password confirm deletion by re-entering it. Google-only
        // accounts (no password) confirm through the explicit UI step instead.
        if ($user->password !== null) {
            $request->validate(['confirm_password' => ['required', 'string']]);
            if (! Hash::check($request->input('confirm_password'), $user->password)) {
                throw ValidationException::withMessages(['confirm_password' => 'Your password is not correct.']);
            }
        }

        Auth::logout();
        // Orders, carts, artwork and support requests are detached (nullOnDelete);
        // the profile and any verification codes cascade away with the user.
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Your account has been deleted.');
    }
}
