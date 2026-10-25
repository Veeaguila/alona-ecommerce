<?php

namespace App\Http\Controllers;

use App\Models\SellerPayoutRequest;
use App\Services\SellerNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPayoutStatusController extends Controller
{
    /**
     * Display all seller payout requests for admin management.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $search = $request->input('search');

        $query = SellerPayoutRequest::with([
            'seller:id,name,store_name,email,contact_no,avatar,store_logo_path,status',
            'processedBy:id,name'
        ])->latest();

        if ($status !== 'all' && in_array($status, ['pending', 'processing', 'approved', 'completed', 'rejected'])) {
            if ($status === 'approved') {
                $query->whereIn('status', ['approved', 'completed']);
            } else {
                $query->where('status', $status);
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('payout_reference', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhereHas('seller', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('store_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('contact_no', 'like', "%{$search}%");
                    });
            });
        }

        $stats = [
            'total_disbursed' => (float) SellerPayoutRequest::whereIn('status', ['approved', 'completed'])->sum('amount'),
            'pending_amount' => (float) SellerPayoutRequest::where('status', 'pending')->sum('amount'),
            'processing_amount' => (float) SellerPayoutRequest::where('status', 'processing')->sum('amount'),
            'pending_count' => SellerPayoutRequest::where('status', 'pending')->count(),
            'processing_count' => SellerPayoutRequest::where('status', 'processing')->count(),
            'approved_count' => SellerPayoutRequest::whereIn('status', ['approved', 'completed'])->count(),
            'rejected_count' => SellerPayoutRequest::where('status', 'rejected')->count(),
            'total_count' => SellerPayoutRequest::count(),
        ];

        return Inertia::render('Admin/PayoutRequests', [
            'payouts' => $query->paginate(20)->withQueryString(),
            'currentStatus' => $status,
            'filters' => [
                'status' => $status,
                'search' => $search ?? '',
            ],
            'stats' => $stats,
            'counts' => [
                'all' => SellerPayoutRequest::count(),
                'pending' => SellerPayoutRequest::where('status', 'pending')->count(),
                'processing' => SellerPayoutRequest::where('status', 'processing')->count(),
                'approved' => SellerPayoutRequest::whereIn('status', ['approved', 'completed'])->count(),
                'rejected' => SellerPayoutRequest::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * SELLER-61: Update payout request status and notify seller.
     */
    public function updateStatus(Request $request, SellerPayoutRequest $payout): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:processing,approved,completed,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:500'],
        ]);

        $newStatus = $validated['status'];
        $reason = $validated['rejection_reason'] ?? null;

        $payout->update([
            'status' => $newStatus,
            'admin_notes' => $validated['admin_notes'] ?? $payout->admin_notes,
            'rejection_reason' => $reason,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        // Send automated notification to seller (SELLER-61)
        SellerNotificationService::sendPayoutStatusAlert($payout, $newStatus, $reason);

        return back()->with('status', "Payout request {$payout->payout_reference} marked as {$newStatus}. Seller has been notified.");
    }
}

