<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LogisticsEmailOtp extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Verify your ALONA logistics email')
            ->line('Your verification code is: '.$this->code)
            ->line('This code expires in five minutes. Requesting another code cancels this one.')
            ->line('If you did not create this account, ignore this email.');
    }
}
