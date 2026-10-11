<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Http\Requests\LoginRiderRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginRiderController extends Controller
{
    public function login(LoginRiderRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->where('usertype', 'rider')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (!in_array($user->status, ['approved', 'pending'], true)) {
            return response()->json(['message' => 'Your account is unavailable.'], 403);
        }

        $rider = $user->rider;
        $approvalStatus = $rider?->approval_status ?? 'pending';
        $isActive = $rider?->is_active ?? false;
        $token = $user->createToken('rider-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'approval_status' => $approvalStatus,
            'is_active' => $isActive,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->usertype,
            ],
            'rider' => $rider ? [
                'id' => $rider->id,
                'name' => $rider->name,
                'approval_status' => $rider->approval_status,
                'is_active' => $rider->is_active,
            ] : null,
        ], 200);
    }
}
