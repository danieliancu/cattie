<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AbandonedCheckoutMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public int $stage) {}

    public function envelope(): Envelope
    {
        $subject = $this->stage === 1
            ? 'You left something behind — finish your Kattie order'
            : 'Still thinking it over? Your Kattie design is waiting';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned-checkout',
            with: [
                'resumeUrl' => URL::signedRoute('checkout.resume', ['order' => $this->order->id]),
                'unsubscribeUrl' => URL::signedRoute('orders.stop-reminders', ['order' => $this->order->id]),
            ],
        );
    }
}
