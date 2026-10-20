<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhoneChangeOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $otpCode,
        public readonly string $newPhone
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->first_name ?? $notifiable->name ?? 'Valued Customer';

        return (new MailMessage)
            ->subject('Verify Your New Phone Number - Alona')
            ->greeting("Hello {$name}!")
            ->line("You requested to change your contact phone number to: {$this->newPhone}.")
            ->line('Please use the 6-digit verification code below to confirm this change:')
            ->line("**{$this->otpCode}**")
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this change, please ignore this notice.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'phone_change_otp',
            'otp_code' => $this->otpCode,
            'new_phone' => $this->newPhone,
            'message' => "Your phone change verification code is {$this->otpCode}",
        ];
    }
}

