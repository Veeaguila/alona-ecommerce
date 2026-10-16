<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminApplicationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | APPLICATION LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = User::query()
            ->whereIn('usertype', ['buyer', 'seller', 'rider', 'sorting_center'])
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | ROLE FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('role') &&
            $request->input('role') !== 'all'
        ) {
            $query->where(
                'usertype',
                $request->input('role')
            );
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
            $query->where(
                'status',
                $request->input('status')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where(
                    'name',
                    'like',
                    "%{$search}%"
                )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'store_name',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Admin/Applications', [
            'title' => 'Applications',

            'eyebrow' => 'Registration management',

            'description' => 'Review buyer, seller, and courier applications, approve valid applications, and reject incomplete or non-compliant applications.',

            'active' => 'applications',

            'applications' => $query
                ->paginate(15)
                ->withQueryString(),

            'filters' => [
                'role' => (string) $request->input(
                    'role',
                    'all'
                ),

                'status' => (string) $request->input(
                    'status',
                    'all'
                ),

                'search' => (string) $request->input(
                    'search',
                    ''
                ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPLICATION DETAILS
    |--------------------------------------------------------------------------
    */

    public function show(User $user)
    {
        abort_unless(
            in_array(
                $user->usertype,
                ['buyer', 'seller', 'rider', 'sorting_center'],
                true
            ),
            404
        );

        $rawDocs = $user->partnerApplication?->documents ?? [];
        if ($user->id_path && empty($rawDocs['government_id']) && empty($rawDocs['id'])) {
            $rawDocs['government_id'] = $user->id_path;
        }
        if ($user->business_permit_path && empty($rawDocs['business_permit'])) {
            $rawDocs['business_permit'] = $user->business_permit_path;
        }

        return Inertia::render('Admin/Applications', [
            'title' => ucfirst($user->usertype).' application',

            'eyebrow' => 'Application review',

            'description' => 'Review the submitted profile details and either approve or reject this application.',

            'active' => 'applications',

            'partnerProfile' => $user->partnerApplication?->only(['details', 'address']),
            'partnerDocuments' => collect($rawDocs)->map(fn ($path, $key) => route('partner.documents', [$user, $key]))->all(),
            'application' => $user->only([
                'id',
                'name',
                'email',
                'usertype',
                'status',
                'rejection_reason',
                'contact_no',
                'address',
                'province',
                'municipality',
                'barangay',
                'street_address',
                'store_name',
                'line_of_business',
                'store_description',
                'created_at',
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE APPLICATION
    |--------------------------------------------------------------------------
    */

    public function approve(
        Request $request,
        User $user
    ): RedirectResponse {
        abort_unless(
            in_array(
                $user->usertype,
                ['buyer', 'seller', 'rider', 'sorting_center'],
                true
            ),
            404
        );

        $user->update([
            'status' => 'approved',
            'rejection_reason' => null,
        ]);

        if ($user->usertype === 'sorting_center') {
            $user->sortingCenter()->update(['approval_status' => 'approved']);
        } elseif ($user->usertype === 'rider') {
            $user->rider()->update(['approval_status' => 'approved']);
        }

        $this->notifyApplicant(
            $user,
            'Your application has been approved.',
            'Your application has been approved. You may now sign in and access your restricted dashboard.'
        );

        return back()->with(
            'status',
            ucfirst($user->usertype).
                ' application approved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT APPLICATION
    |--------------------------------------------------------------------------
    */

    public function reject(
        Request $request,
        User $user
    ): RedirectResponse {
        abort_unless(
            in_array(
                $user->usertype,
                ['buyer', 'seller', 'rider', 'sorting_center'],
                true
            ),
            404
        );

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $user->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
        ]);

        if ($user->usertype === 'sorting_center') {
            $user->sortingCenter()->update(['approval_status' => 'rejected']);
        } elseif ($user->usertype === 'rider') {
            $user->rider()->update(['approval_status' => 'rejected']);
        }

        $this->notifyApplicant(
            $user,
            'Your application was not approved.',
            "Your application was rejected for the following reason:\n\n".
                $validated['reason']
        );

        return back()->with(
            'status',
            ucfirst($user->usertype).
                ' application rejected.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EMAIL NOTIFICATION
    |--------------------------------------------------------------------------
    */

    protected function notifyApplicant(
        User $user,
        string $subject,
        string $message
    ): void {
        if (! filled($user->email)) {
            return;
        }

        try {
            Mail::raw(
                $message,
                function ($mail) use ($user, $subject) {
                    $mail
                        ->to($user->email)
                        ->subject($subject);
                }
            );
        } catch (\Throwable $exception) {
            Log::warning('Application decision email could not be sent.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
