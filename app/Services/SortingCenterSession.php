<?php

namespace App\Services;

use App\Models\User;

/** Response shape shared by password-authenticated login and newly created accounts. */
class SortingCenterSession
{
    public function issue(User $user, string $message = 'Login successful.'): array
    {
        $access = app(HubAccess::class);
        $staff = $access->membership($user);
        $center = $staff ? $access->context($user)['center'] : $user->sortingCenter;
        return [
            'message' => $message,
            'token' => $user->createToken('sorting-center-token')->plainTextToken,
            'account_status' => $user->status,
            'approval_status' => $center?->approval_status ?? 'pending',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->usertype],
            'sorting_center' => $center?->only(['id', 'business_name', 'approval_status']),
            'operator' => $staff ? $access->identity($user) : ['role' => 'company_admin', 'hub' => null],
        ];
    }
}
