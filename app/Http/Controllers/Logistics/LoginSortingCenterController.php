<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Http\Requests\LoginSortingCenterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginSortingCenterController extends Controller
{
    public function login(LoginSortingCenterRequest $request): JsonResponse
    {
        // Attempt to find user with usertype = sorting_center
        $user = User::where('email', $request->email)
            ->where('usertype', 'sorting_center')
            ->first();

        // Check user existence and password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (!in_array($user->status, ['approved', 'pending'], true)) {
            $onboarding = ($user->partnerApplication?->details['registration_version'] ?? null) === 2;
            if (!$onboarding || !in_array($user->status, ['unverified', 'rejected'], true)) {
                return response()->json(['message' => 'Your account is unavailable.'], 403);
            }
        }
        // Approval middleware still denies operations; this permits only authenticated onboarding.
        return response()->json(app(\App\Services\SortingCenterSession::class)->issue($user));
    }
}
