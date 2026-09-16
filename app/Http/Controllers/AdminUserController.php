<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    /**
     * Display all marketplace users.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->whereIn('usertype', ['buyer', 'seller', 'rider', 'admin']);

        /*
        |--------------------------------------------------------------------------
        | ROLE FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('role') &&
            $request->input('role') !== 'all'
        ) {
            $query->where('usertype', $request->input('role'));
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status') &&
            $request->input('status') !== 'all'
        ) {
            $query->where('status', $request->input('status'));
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('store_name', 'like', "%{$search}%")
                    ->orWhere('contact_no', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $users = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'title' => 'Users',
            'eyebrow' => 'User management',
            'description' => 'Review buyers, sellers, couriers, and administrators and manage their marketplace access.',
            'active' => 'users',

            'users' => $users,

            'filters' => [
                'search' => (string) $request->input('search', ''),
                'role' => (string) $request->input('role', 'all'),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * Display a specific user profile.
     */
    public function show(User $user)
    {
        abort_unless(
            in_array($user->usertype, ['buyer', 'seller', 'rider', 'admin'], true),
            404
        );

        return Inertia::render('Admin/Users', [
            'title' => 'User profile',
            'eyebrow' => 'User management',
            'description' => 'Review the account details, role, status, and marketplace access for this user.',
            'active' => 'users',

            'user' => $user->only([
                'id',
                'name',
                'first_name',
                'middle_initial',
                'last_name',
                'email',
                'usertype',
                'status',
                'rejection_reason',
                'contact_no',
                'birthday',
                'age',
                'sex',
                'address',
                'province',
                'municipality',
                'barangay',
                'street_address',
                'store_name',
                'store_description',
                'line_of_business',
                'store_logo_path',
                'shipping_fee',
                'return_policy_days',
                'bank_name',
                'bank_account_name',
                'notify_new_order',
                'notify_messages',
                'created_at',
                'updated_at',
            ]),
        ]);
    }

    /**
     * Update a user's account status.
     */
    public function updateStatus(
        Request $request,
        User $user
    ): RedirectResponse {
        abort_unless(
            in_array($user->usertype, ['buyer', 'seller', 'rider', 'admin'], true),
            404
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent the logged-in administrator from disabling themselves.
        |--------------------------------------------------------------------------
        */

        if (
            auth()->id() === $user->id &&
            in_array(
                $request->input('status'),
                ['suspended', 'deactivated', 'rejected'],
                true
            )
        ) {
            return back()->withErrors([
                'status' => 'You cannot suspend, deactivate, or reject your own administrator account.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'status' => [
                'required',
                'in:approved,pending,suspended,deactivated,rejected',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | SAVE STATUS + REASON
        |--------------------------------------------------------------------------
        */

        $reason = trim((string) ($validated['reason'] ?? ''));

        $user->update([
            'status' => $validated['status'],
            'rejection_reason' => $reason !== ''
                ? $reason
                : null,
        ]);

        return back()->with(
            'status',
            'Account status updated successfully.'
        );
    }
}