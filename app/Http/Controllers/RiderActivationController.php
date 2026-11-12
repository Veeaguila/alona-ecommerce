<?php

namespace App\Http\Controllers;

use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RiderActivationController extends Controller
{
    /**
     * Show the rider activation password setup form or display expired/used status.
     * GET /rider/activate/{token}
     */
    public function show(Request $request, string $token): JsonResponse|InertiaResponse
    {
        $rider = Rider::with(['user', 'sortingCenter'])
            ->where('activation_token', $token)
            ->first();

        if (! $rider) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The activation link is invalid or has already been used.',
                    'status' => 'invalid',
                ], 404);
            }

            return Inertia::render('Auth/RiderActivation', [
                'status' => 'invalid',
                'message' => 'The activation link is invalid or has already been used.',
            ]);
        }

        if ($rider->activation_token_expires_at?->isPast()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The activation link has expired.',
                    'status' => 'expired',
                ], 410);
            }

            return Inertia::render('Auth/RiderActivation', [
                'status' => 'expired',
                'message' => 'The activation link has expired.',
            ]);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Activation link is valid.',
                'status' => 'valid',
                'data' => [
                    'rider_name' => $rider->name,
                    'email' => $rider->user->email,
                    'company_name' => $rider->sortingCenter?->business_name ?? 'Logistics',
                ],
            ], 200);
        }

        return Inertia::render('Auth/RiderActivation', [
            'status' => 'valid',
            'token' => $token,
            'email' => $rider->user->email,
            'rider_name' => $rider->name,
            'company_name' => $rider->sortingCenter?->business_name ?? 'Logistics',
        ]);
    }

    /**
     * Activate the rider account by setting a new password.
     * POST /rider/activate/{token}
     */
    public function activate(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $rider = Rider::with(['user', 'sortingCenter'])
            ->where('activation_token', $token)
            ->first();

        if (! $rider) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The activation link is invalid or has already been used.',
                ], 422);
            }

            return back()->withErrors(['token' => 'The activation link is invalid or has already been used.']);
        }

        if ($rider->activation_token_expires_at?->isPast()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The activation link has expired.',
                ], 422);
            }

            return back()->withErrors(['token' => 'The activation link has expired.']);
        }

        $rules = [
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ];

        $messages = [
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The passwords do not match.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            throw new ValidationException($validator);
        }

        DB::transaction(function () use ($rider, $request) {
            $user = $rider->user;
            $user->password = Hash::make($request->password);
            $user->status = 'approved';
            $user->email_verified_at = now();
            $user->save();

            $rider->update([
                'approval_status' => 'approved',
                'is_active' => true,
                'activation_token' => null,
                'activation_token_expires_at' => null,
            ]);
        });

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Account activated successfully. You can now log in.',
                'status' => 'active',
            ], 200);
        }

        return redirect('/?auth=login')->with('success', 'Account activated successfully. You can now log in.');
    }
}

