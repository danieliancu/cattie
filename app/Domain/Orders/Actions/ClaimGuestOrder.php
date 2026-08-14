<?php

namespace App\Domain\Orders\Actions;

use App\Models\Order;
use App\Models\User;
use App\Support\GuestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClaimGuestOrder
{
    public function __construct(private GuestContext $guest) {}

    public function handle(string $number, Request $request, User $user): ?Order
    {
        $order = Order::query()->where('number', $number)->first();
        if (! $order || ! $this->guest->owns($order->access_token_hash, $request) || strcasecmp($order->email, $user->email) !== 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $user) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->user_id !== null && $order->user_id !== $user->id) {
                return null;
            }
            if ($order->user_id === null) {
                $order->update(['user_id' => $user->id]);
            }

            return $order->fresh();
        });
    }
}
