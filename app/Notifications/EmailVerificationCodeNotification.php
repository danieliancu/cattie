<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Kattie verification code: '.$this->code)
            ->greeting('Confirm your email')
            ->line('Use this code to finish creating your Kattie account:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in 10 minutes.')
            ->line('If you did not try to create an account, you can safely ignore this email.');
    }
}
