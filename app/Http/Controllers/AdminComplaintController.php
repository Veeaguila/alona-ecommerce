<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\Commission;
use App\Models\Complaint;
use App\Models\RefundLedger;
use App\Models\SellerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
                'order:id,order_number,total,status,payment_status,refund_status,refund_amount',
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
            'order:id,order_number,total,status,payment_status,refund_status,refund_amount',
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

            'issue_refund' => [
                'nullable',
                'boolean',
            ],

            'refund_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],
        ]);

        $refundIssued = false;
        $refundAmount = 0.0;

        DB::transaction(function () use ($request, $complaint, $validated, &$refundIssued, &$refundAmount) {
            $complaint->update([
                'status' => $validated['status'],
                'resolution' => trim($validated['resolution']),
                'reviewed_by' => $request->user()->id,
            ]);

            // Execute force refund if requested and order is linked
            if ($validated['status'] === 'resolved' && $request->boolean('issue_refund') && $complaint->order_id) {
                $order = $complaint->order()->lockForUpdate()->first();
                if ($order && $order->refund_status !== 'refunded') {
                    $orderTotal = (float) ($order->total ?? 0);
                    $requestedRefund = isset($validated['refund_amount']) && (float) $validated['refund_amount'] > 0
                        ? (float) $validated['refund_amount']
                        : $orderTotal;

                    $refundAmount = round(min($requestedRefund, $orderTotal), 2);

$firstItem = $order->items()->where('product_id', $complaint->product_id)->first() ?? $order->items()->first();

                    RefundLedger::create([
                        'refund_reference' => 'REF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                        'order_id' => $order->id,
                        'order_item_id' => $firstItem?->id,
                        'order_return_request_id' => null,
                        'seller_id' => $complaint->seller_id ?? $order->items()->first()?->product?->seller_id,
                        'buyer_id' => $complaint->buyer_id ?? $order->user_id,
                        'original_amount' => $orderTotal,
                        'refunded_amount' => $refundAmount,
                        'refund_type' => 'dispute_resolution',
                        'refund_method' => $order->payment_method ?? 'original_payment',
                        'status' => 'completed',
                        'reason' => $complaint->subject,
                        'notes' => 'Admin Dispute Resolution: ' . trim($validated['resolution']),
                        'processed_by' => $request->user()->id,
                        'processed_at' => now(),
                    ]);

                    $order->update([
                        'refund_status' => 'refunded',
                        'refund_amount' => $refundAmount,
                        'payment_status' => 'refunded',
                    ]);

                    $order->items()->update([
                        'status' => 'refunded',
                    ]);

                    Commission::where('order_id', $order->id)->update([
                        'status' => 'cancelled',
                    ]);

                    $refundIssued = true;

                    // Notify Seller of debit/refund
                    if (Schema::hasTable('seller_notifications') && ($complaint->seller_id || $order->items()->first()?->product?->seller_id)) {
                        $sellerId = $complaint->seller_id ?? $order->items()->first()?->product?->seller_id;
                        try {
                            SellerNotification::create([
                                'user_id' => $sellerId,
                                'type' => 'refund',
                                'title' => 'Dispute Refund Debited',
                                'message' => "Dispute for Order #{$order->order_number} resolved by Admin. Refund of ₱" . number_format($refundAmount, 2) . " debited.",
                                'url' => route('seller.orders'),
                            ]);
                        } catch (\Throwable $e) {
                            // Ignore notification error
                        }
                    }
                }
            }
        });

        if (Schema::hasTable('buyer_notifications') && $complaint->buyer_id) {
            try {
                $buyerMsg = "Your complaint '{$complaint->subject}' has been marked as {$validated['status']}. Response: " . Str::limit($validated['resolution'], 120);
                if ($refundIssued) {
                    $buyerMsg .= " A force-refund of ₱" . number_format($refundAmount, 2) . " has been issued to your account.";
                }

                BuyerNotification::create([
                    'user_id' => $complaint->buyer_id,
                    'title' => $refundIssued ? 'Dispute Resolved & Refund Issued' : ("Complaint status: " . ucfirst($validated['status'])),
                    'type' => $refundIssued ? 'refund' : 'complaint',
                    'message' => $buyerMsg,
                    'action_url' => route('buyer.complaints'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification error
            }
        }

        $flashMessage = $refundIssued
            ? 'Complaint resolved and force-refund of ₱' . number_format($refundAmount, 2) . ' processed successfully.'
            : 'Complaint resolution saved successfully.';

        return back()->with('status', $flashMessage);
    }
}