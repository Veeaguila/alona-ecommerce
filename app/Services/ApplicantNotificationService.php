<?php

namespace App\Services;

use App\Models\BuyerNotification;
use App\Models\RiderNotification;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ApplicantNotificationService
{
    /**
     * Notify applicant of application approval.
     */
    public function notifyApproval(User $user): bool
    {
        $subject = 'Your application has been approved.';
        $message = "Congratulations! Your " . ucfirst($user->usertype) . " application has been approved. You may now sign in and access your restricted dashboard.";

        return $this->dispatch($user, $subject, $message);
    }

    /**
     * Notify applicant of application rejection.
     */
    public function notifyRejection(User $user, string $reason): bool
    {
        $subject = 'Your application was not approved.';
        $message = "Your " . ucfirst($user->usertype) . " application was rejected for the following reason:\n\n" . $reason;

        return $this->dispatch($user, $subject, $message);
    }

    /**
     * Dispatch both in-app notification and email.
     */
    public function dispatch(User $user, string $subject, string $message): bool
    {
        // 1. Dispatch in-app notification first (always available even if mail is down)
        $this->dispatchInAppNotification($user, $subject, $message);

        // 2. Dispatch email
        if (! filled($user->email)) {
            return false;
        }

        try {
            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email)
                    ->subject($subject);
            });

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Application decision email could not be sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Save in-app notification record.
     */
    protected function dispatchInAppNotification(User $user, string $subject, string $message): void
    {
        try {
            if ($user->usertype === 'buyer' && Schema::hasTable('buyer_notifications')) {
                BuyerNotification::create([
                    'user_id' => $user->id,
                    'title' => $subject,
                    'type' => 'application_decision',
                    'message' => $message,
                ]);
            } elseif ($user->usertype === 'seller' && Schema::hasTable('seller_notifications')) {
                SellerNotification::create([
                    'user_id' => $user->id,
                    'title' => $subject,
                    'type' => 'application_decision',
                    'message' => $message,
                    'url' => route('seller.dashboard'),
                ]);
            } elseif ($user->usertype === 'rider' && Schema::hasTable('rider_notifications')) {
                RiderNotification::create([
                    'user_id' => $user->id,
                    'type' => 'application_decision',
                    'data' => [
                        'title' => $subject,
                        'message' => $message,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('In-app application decision notification could not be created.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

