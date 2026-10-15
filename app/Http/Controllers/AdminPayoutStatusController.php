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

        $query = SellerPayoutRequest::with(['seller', 'processedBy'])->latest();

        if ($status !== 'all' && in_array($status, ['pending', 'processing', 'approved', 'completed', 'rejected'])) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/PayoutRequests', [
            'payouts' => $query->paginate(20)->withQueryString(),
            'currentStatus' => $status,
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

