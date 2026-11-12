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
        $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));
        if (! $name) {
            $name = $notifiable->name ?? 'Valued Customer';
        }

        return (new MailMessage)
            ->subject('Your Alona 6-Digit Email Verification Code: ' . $this->otpCode)
            ->view('emails.otp-verification', [
                'otpCode' => $this->otpCode,
                'userName' => $name,
                'title' => 'Verify Your Email Address',
                'introText' => 'Thank you for registering on Alona. Please use the 6-digit One-Time Password (OTP) below to verify your email address and activate your account:',
                'subject' => 'Your Alona 6-Digit Email Verification Code',
            ]);
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

