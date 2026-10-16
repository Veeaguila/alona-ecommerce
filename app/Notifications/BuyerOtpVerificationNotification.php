<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuyerOtpVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $otpCode)
    {
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
        return (new MailMessage)
            ->subject('Your Alona 6-Digit Email Verification Code')
            ->greeting('Hello ' . ($notifiable->first_name ?? $notifiable->name) . '!')
            ->line('Thank you for registering as a Buyer on Alona.')
            ->line('Please use the following 6-digit One-Time Password (OTP) to verify your email address:')
            ->line('**' . $this->otpCode . '**')
            ->line('This verification code will expire in 5 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp_code' => $this->otpCode,
        ];
    }
}

