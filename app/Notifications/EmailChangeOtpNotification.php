<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $otpCode,
        public readonly ?object $user = null
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
        $name = $this->user?->first_name ?? $this->user?->name ?? 'Valued Customer';

        return (new MailMessage)
            ->subject('Verify Your New Email Address - Alona: ' . $this->otpCode)
            ->view('emails.otp-verification', [
                'otpCode' => $this->otpCode,
                'userName' => $name,
                'title' => 'Confirm Email Address Change',
                'introText' => 'You recently requested to update the email address linked to your Alona account. Please use the 6-digit verification code below to authorize this change:',
                'subject' => 'Verify Your New Email Address - Alona',
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
            'type' => 'email_change_otp',
            'otp_code' => $this->otpCode,
            'message' => "Your email change verification code is {$this->otpCode}",
        ];
    }
}

