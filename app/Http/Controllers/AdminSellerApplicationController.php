<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSellerApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = User::query()
            ->where('usertype', 'seller')
            ->whereIn('status', ['pending', 'rejected'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Applications', [
            'title' => 'Seller applications',
            'eyebrow' => 'Seller management',
            'description' => 'Approve, reject, verify, and support the next generation of stores.',
            'active' => 'applications',
            'applications' => $applications,
            'filters' => [
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    public function approve(User $user)
    {
        abort_unless($user->usertype === 'seller', 404);

        $user->update(['status' => 'approved']);

        return back()->with('status', 'Seller application approved.');
    }

    public function reject(Request $request, User $user)
    {
        abort_unless($user->usertype === 'seller', 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user->update([
            'status' => 'rejected',
        ]);

        return back()->with('status', 'Seller application rejected.');
    }
}
