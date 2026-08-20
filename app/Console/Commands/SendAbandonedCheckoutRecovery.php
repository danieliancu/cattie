<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Mail\AbandonedCheckoutMail;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCheckoutRecovery extends Command
{
    protected $signature = 'orders:send-abandoned-recovery';

    protected $description = 'Email a two-step recovery sequence for checkouts left unpaid.';

    // First reminder ~1h after checkout, second ~24h; never touch orders older
    // than the cutoff so a first run cannot flood historic abandoned orders.
    private const FIRST_AFTER_MINUTES = 60;

    private const SECOND_AFTER_MINUTES = 24 * 60;

    private const CUTOFF_DAYS = 3;

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        Order::query()
            ->where('status', OrderStatus::AwaitingPayment)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('recovery_unsubscribed_at')
            ->where('created_at', '>=', $now->copy()->subDays(self::CUTOFF_DAYS))
            ->where('created_at', '<=', $now->copy()->subMinutes(self::FIRST_AFTER_MINUTES))
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($now, &$sent) {
                foreach ($orders as $order) {
                    $age = $order->created_at;

                    if ($order->recovery_first_sent_at === null) {
                        Mail::to($order->email)->queue(new AbandonedCheckoutMail($order, 1));
                        $order->forceFill(['recovery_first_sent_at' => $now])->save();
                        $sent++;

                        continue;
                    }

                    if ($order->recovery_second_sent_at === null && $age->lte($now->copy()->subMinutes(self::SECOND_AFTER_MINUTES))) {
                        Mail::to($order->email)->queue(new AbandonedCheckoutMail($order, 2));
                        $order->forceFill(['recovery_second_sent_at' => $now])->save();
                        $sent++;
                    }
                }
            });

        $this->info("Queued {$sent} abandoned-checkout recovery email(s).");

        return self::SUCCESS;
    }
}
