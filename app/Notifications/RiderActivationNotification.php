<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderActivationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $companyName = 'ALONA Logistics'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = url('/rider/activate/'.$this->token);

        return (new MailMessage)
            ->subject('Activate your Rider Account - '.$this->companyName)
            ->greeting('Hello, '.$notifiable->first_name ?? $notifiable->name.'!')
            ->line('You have been invited to join '.$this->companyName.' as a delivery rider.')
            ->line('Please activate your account by setting your password. This activation link is valid for 24 hours.')
            ->action('Activate Account', $activationUrl)
            ->line('If you did not expect this invitation, please disregard this email.');
    }
}

