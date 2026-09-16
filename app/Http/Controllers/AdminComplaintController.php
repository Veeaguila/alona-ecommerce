<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminComplaintController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Complaints List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = Complaint::query()
            ->with([
                'buyer:id,name,email',
                'seller:id,name,store_name,email',
                'courier:id,name',
                'order:id,order_number',
            ])
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Status Filter
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
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                $request->input('search')
            );

            $query->where(function ($q) use ($search) {
                $q->where(
                    'subject',
                    'like',
                    "%{$search}%"
                )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'buyer',
                        function ($buyerQuery) use ($search) {
                            $buyerQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    )
                    ->orWhereHas(
                        'seller',
                        function ($sellerQuery) use ($search) {
                            $sellerQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'store_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    )
                    ->orWhereHas(
                        'order',
                        function ($orderQuery) use ($search) {
                            $orderQuery->where(
                                'order_number',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $complaints = $query
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return Inertia::render(
            'Admin/Complaints',
            [
                'title' => 'Complaints and disputes',

                'eyebrow' => 'Dispute management',

                'description' =>
                    'Review complaint details, coordinating with the buyer, seller, and courier to reach a fair resolution.',

                'active' => 'complaints',

                'complaints' => $complaints,

                'filters' => [
                    'status' =>
                        (string) $request->input(
                            'status',
                            'all'
                        ),

                    'search' =>
                        (string) $request->input(
                            'search',
                            ''
                        ),
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Complaint Details
    |--------------------------------------------------------------------------
    */

    public function show(
        Complaint $complaint
    ) {
        $complaint->load([
            'buyer:id,name,email,contact_no',
            'seller:id,name,store_name,email,contact_no',
            'courier:id,name,email,contact_no',
            'order:id,order_number',
            'reviewer:id,name,email',
        ]);

        return Inertia::render(
            'Admin/ComplaintDetail',
            [
                'title' => 'Complaint review',

                'eyebrow' => 'Dispute management',

                'description' =>
                    'Assess the submitted evidence and resolution status for this dispute.',

                'active' => 'complaints',

                'complaint' => $complaint,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve / Update Complaint
    |--------------------------------------------------------------------------
    */

    public function resolve(
        Request $request,
        Complaint $complaint
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,reviewing,resolved,rejected',
            ],

            'resolution' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $complaint->update([
            'status' =>
                $validated['status'],

            'resolution' =>
                trim($validated['resolution']),

            'reviewed_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'status',
            'Complaint resolution saved successfully.'
        );
    }
}