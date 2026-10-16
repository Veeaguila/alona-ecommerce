<?php

namespace App\Services;

use App\Models\User;

class AccountDestination
{
    public function route(User $user): string
    {
        if ($user->usertype === 'buyer' && ! $user->hasVerifiedEmail()) {
            return 'verification.otp';
        }

        if ($user->status !== 'approved') {
            return 'application.status';
        }

        if ($user->usertype === 'sorting_center' && $user->sortingCenter?->approval_status !== 'approved') {
            return 'application.status';
        }
        if ($user->usertype === 'rider' && ($user->rider?->approval_status !== 'approved' || ! $user->rider?->is_active)) {
            return 'application.status';
        }
        if ($user->partnerApplication()->exists() && ! $user->hasVerifiedEmail()) {
            return 'application.status';
        }

        return match ($user->usertype) {
            'admin' => 'admin.dashboard',
            'seller' => 'seller.dashboard',
            'sorting_center' => 'sorting-center.dashboard',
            'rider' => 'rider.dashboard',
            'buyer' => 'buyer.dashboard',
            default => 'application.status',
        };
    }
}
