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
        $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));
        if (! $name) {
            $name = $notifiable->name ?? 'Partner Representative';
        }

        return (new MailMessage)
            ->subject('Verify your ALONA Logistics Email: ' . $this->code)
            ->view('emails.otp-verification', [
                'otpCode' => $this->code,
                'userName' => $name,
                'title' => 'Logistics Partner Email Verification',
                'introText' => 'Thank you for onboarding with the Alona Logistics Network. Please enter the 6-digit verification code below to verify your partner email:',
                'subject' => 'Verify your ALONA Logistics Email',
            ]);
    }
}
