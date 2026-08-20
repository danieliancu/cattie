<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class OrderRecoveryController extends Controller
{
    /**
     * Signed link from a recovery email. Re-mints a guest token so the visitor
     * regains access to their own order, then drops them on the payment page.
     */
    public function resume(Order $order, GuestContext $guest): RedirectResponse
    {
        $target = match ($order->status) {
            OrderStatus::AwaitingPayment => route('checkout.payment', $order->number),
            OrderStatus::Paid => route('orders.confirmation', $order->number),
            default => null,
        };

        if ($target === null) {
            return redirect()->route('home')->with('status', 'This order is no longer available for payment.');
        }

        $token = Str::random(64);
        $order->forceFill(['access_token_hash' => $guest->hash($token)])->save();

        return redirect($target)->withCookie($guest->cookie($token));
    }

    /** Signed link from a recovery email — stop the remaining reminders for this order. */
    public function stopReminders(Order $order): View
    {
        if ($order->recovery_unsubscribed_at === null) {
            $order->forceFill(['recovery_unsubscribed_at' => now()])->save();
        }

        return view('storefront.orders.reminders-stopped', ['order' => $order]);
    }
}
