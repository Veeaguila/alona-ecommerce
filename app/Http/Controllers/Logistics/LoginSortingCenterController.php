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
            return response()->json(['message' => 'Your account is unavailable.'], 403);
        }

        // Get linked sorting center record and approval status
        $sortingCenter = $user->sortingCenter;
        $approvalStatus = $sortingCenter ? $sortingCenter->approval_status : 'pending';

        // Issue Sanctum token
        $token = $user->createToken('sorting-center-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'approval_status' => $approvalStatus,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->usertype,
            ],
            'sorting_center' => $sortingCenter ? [
                'id' => $sortingCenter->id,
                'business_name' => $sortingCenter->business_name,
                'approval_status' => $sortingCenter->approval_status,
            ] : null,
        ], 200);
    }
}
