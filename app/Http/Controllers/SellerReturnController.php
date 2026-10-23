<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundLedger;
use App\Models\SellerNotification;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SellerReturnController extends Controller
{
    /**
     * Display the Seller Returns & Refunds Center (SELLER-36, SELLER-37, SELLER-41).
     */
    public function index(Request $request): Response
    {
        OrderReturnRequest::autoApproveExpiredPendingRequests();

        $sellerId = $request->user()->id;

        $status = $request->input('status', 'all');
        $type = $request->input('type', 'all');
        $tab = $request->input('tab', 'returns'); // 'returns' or 'ledger'
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'latest');

        // Base query for return requests
        $query = OrderReturnRequest::query()
            ->where('seller_id', $sellerId)
            ->with([
                'order:id,order_number,created_at,payment_method,payment_status,total',
                'orderItem',
                'orderItem.product:id,name,image_path',
                'orderItem.variant:id,product_id,color,size,image_path',
                'buyer:id,name,email,avatar,contact_number',
                'refundLedger',
            ]);

        // Status counts for tabs (SELLER-37 / SEL-RET-001 - SEL-RET-006)
        $statusCounts = [
            'all' => OrderReturnRequest::where('seller_id', $sellerId)->count(),
            'pending' => OrderReturnRequest::where('seller_id', $sellerId)->where('status', 'pending')->count(),
            'approved' => OrderReturnRequest::where('seller_id', $sellerId)->where('status', 'approved')->count(),
            'in_dispute' => OrderReturnRequest::where('seller_id', $sellerId)->where('status', 'in_dispute')->count(),
            'rejected' => OrderReturnRequest::where('seller_id', $sellerId)->where('status', 'rejected')->count(),
            'item_returned' => OrderReturnRequest::where('seller_id', $sellerId)->where('status', 'item_returned')->count(),
            'refunded' => OrderReturnRequest::where('seller_id', $sellerId)->whereIn('status', ['refunded', 'completed'])->count(),
        ];

        // Status filter
        if ($status !== 'all') {
            if ($status === 'refunded') {
                $query->whereIn('status', ['refunded', 'completed']);
            } else {
                $query->where('status', $status);
            }
        }

        // Type filter (return_and_refund vs refund_only)
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        // Search filter (Order number, buyer name/email, product name)
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', function ($oq) use ($search) {
                    $oq->where('order_number', 'like', "%{$search}%");
                })->orWhereHas('buyer', function ($bq) use ($search) {
                    $bq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('orderItem', function ($iq) use ($search) {
                    $iq->where('product_name', 'like', "%{$search}%");
                })->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('return_tracking_number', 'like', "%{$search}%");
            });
        }

        // Sorting
        switch ($sort) {
            case 'oldest':
                $query->oldest('created_at');
                break;
            case 'amount_desc':
                $query->orderByDesc('requested_amount');
                break;
            case 'amount_asc':
                $query->orderBy('requested_amount');
                break;
            case 'latest':
            default:
                $query->latest('created_at');
                break;
        }

        $returnRequests = $query->paginate(15)->withQueryString();

        // SELLER-41: Refund History Ledger
        $ledgerQuery = RefundLedger::query()
            ->where('seller_id', $sellerId)
            ->with([
                'order:id,order_number',
                'orderItem',
                'orderItem.product:id,name,image_path',
                'orderItem.variant:id,product_id,color,size,image_path',
                'buyer:id,name,email,avatar',
                'returnRequest:id,status,type,reason',
            ]);

        if ($search !== '') {
            $ledgerQuery->where(function ($lq) use ($search) {
                $lq->where('refund_reference', 'like', "%{$search}%")
                   ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                   ->orWhereHas('buyer', fn ($bq) => $bq->where('name', 'like', "%{$search}%"))
                   ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $refundLedger = $ledgerQuery->latest('processed_at')->paginate(15)->withQueryString();

        // Calculate KPI Statistics
        $totalRefundedAmount = (float) RefundLedger::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->sum('refunded_amount');

        $totalRefundsCount = RefundLedger::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->count();

        // Average resolution time in hours (database-agnostic)
        $resolvedRows = OrderReturnRequest::where('seller_id', $sellerId)
            ->whereNotNull('resolved_at')
            ->select(['created_at', 'resolved_at'])
            ->get();
        $avgResolutionHours = $resolvedRows->count() > 0
            ? round((float) $resolvedRows->avg(fn ($r) => $r->created_at && $r->resolved_at ? $r->created_at->diffInHours($r->resolved_at) : 0), 1)
            : 0;

        // Overall return rate %
        $totalSellerOrders = OrderItem::query()->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->count();
        $returnRatePercentage = $totalSellerOrders > 0
            ? round(($statusCounts['all'] / $totalSellerOrders) * 100, 1)
            : 0;

        $ledgerStats = [
            'total_refunded_amount' => $totalRefundedAmount,
            'total_refunds_count' => $totalRefundsCount,
            'avg_resolution_hours' => $avgResolutionHours,
            'return_rate_percentage' => $returnRatePercentage,
        ];

        // Store default return address
        $user = $request->user();
        $storeAddress = trim(
            implode(', ', array_filter([
                $user->street_address ?? $user->address ?? null,
                $user->barangay,
                $user->municipality,
                $user->province,
            ]))
        );

        return Inertia::render('Seller/Returns', [
            'returnRequests' => $returnRequests,
            'refundLedger' => $refundLedger,
            'statusCounts' => $statusCounts,
            'ledgerStats' => $ledgerStats,
            'activeTab' => $tab,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'search' => $search,
                'sort' => $sort,
                'tab' => $tab,
            ],
            'storeAddress' => $storeAddress,
        ]);
    }

    /**
     * View a single return request with evidence (SEL-RET-001).
     */
    public function show(Request $request, OrderReturnRequest $returnRequest)
    {
        abort_unless((int) $returnRequest->seller_id === (int) $request->user()->id, 403);

        $returnRequest->load([
            'order:id,order_number,created_at,payment_method,payment_status,total',
            'orderItem',
            'orderItem.product:id,name,image_path',
            'orderItem.variant:id,product_id,color,size,image_path',
            'buyer:id,name,email,avatar,contact_number',
            'refundLedger',
        ]);

        if ($request->wantsJson()) {
            return response()->json($returnRequest);
        }

        return redirect()->route('seller.returns', [
            'search' => $returnRequest->order?->order_number ?? $returnRequest->id,
        ]);
    }

    /**
     * Seller approves or rejects a return request (SELLER-38 / SEL-RET-002, SEL-RET-003, SEL-RET-004, SEL-RET-005).
     */
    public function decision(Request $request, OrderReturnRequest $returnRequest): RedirectResponse
    {
        abort_unless((int) $returnRequest->seller_id === (int) $request->user()->id, 403);

        // SEL-RET-005: Once in dispute or already decided, seller cannot approve or reject
        if ($returnRequest->status !== 'pending') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => "This return request is {$returnRequest->status_label} and can no longer be approved or rejected.",
            ]);
        }

        $decision = $request->input('decision');
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            // Check if action was specified via route name or action param
            if ($request->routeIs('seller.returns.approve')) {
                $decision = 'approved';
            } elseif ($request->routeIs('seller.returns.reject')) {
                $decision = 'rejected';
            } else {
                $validated = $request->validate([
                    'decision' => 'required|in:approved,rejected',
                ]);
                $decision = $validated['decision'];
            }
        }

        $order = $returnRequest->order;
        $orderItem = $returnRequest->orderItem;

        if ($decision === 'approved') {
            // Check if seller wants to immediately refund a refund_only request
            if ($returnRequest->type === 'refund_only' && $request->boolean('refund_now')) {
                DB::transaction(function () use ($returnRequest, $order, $orderItem, $request) {
                    $refundAmount = (float) $returnRequest->requested_amount;

                    $returnRequest->update([
                        'decision' => 'approved',
                        'status' => 'refunded',
                        'refunded_amount' => $refundAmount,
                        'refund_method' => 'original_payment',
                        'seller_response' => $request->input('seller_response') ?? 'Refund approved directly without item return.',
                        'seller_responded_at' => now(),
                        'resolved_at' => now(),
                    ]);

                    // Create itemized refund ledger entry (SELLER-41)
                    RefundLedger::create([
                        'refund_reference' => 'REF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                        'order_return_request_id' => $returnRequest->id,
                        'order_id' => $returnRequest->order_id,
                        'order_item_id' => $returnRequest->order_item_id,
                        'seller_id' => $returnRequest->seller_id,
                        'buyer_id' => $returnRequest->buyer_id,
                        'original_amount' => $returnRequest->requested_amount,
                        'refunded_amount' => $refundAmount,
                        'refund_type' => 'refund_only',
                        'refund_method' => 'original_payment',
                        'status' => 'completed',
                        'reason' => $returnRequest->reason,
                        'notes' => $request->input('seller_response') ?? 'Instant refund approved by seller',
                        'processed_by' => $request->user()->id,
                        'processed_at' => now(),
                    ]);

                    if ($orderItem) {
                        $orderItem->update(['status' => 'refunded']);
                    }
                });

                // Notify buyer
                if (Schema::hasTable('buyer_notifications')) {
                    BuyerNotification::create([
                        'user_id' => $returnRequest->buyer_id,
                        'title' => 'Refund Request Approved',
                        'message' => "Your refund request for Order #{$order->order_number} has been approved. A refund of ₱" . number_format($returnRequest->requested_amount, 2) . " has been issued.",
                    ]);
                }

                return back()->with('status', 'Refund request approved and processed immediately.');
            }

            $user = $request->user();
            $defaultStoreAddress = trim(
                implode(', ', array_filter([
                    $user->street_address ?? $user->address ?? null,
                    $user->barangay,
                    $user->municipality,
                    $user->province,
                ]))
            ) ?: 'Seller Store Warehouse';

            $returnInstructions = $request->input('return_instructions') ?: 'Please pack the item securely in its original packaging and ship to our return warehouse.';
            $warehouseAddress = $request->input('return_warehouse_address') ?: $defaultStoreAddress;

            // Normal Return & Refund Approval: Buyer authorized to ship item back (SEL-RET-002)
            $returnRequest->update([
                'decision' => 'approved',
                'status' => 'approved',
                'return_instructions' => $returnInstructions,
                'return_warehouse_address' => $warehouseAddress,
                'seller_response' => $request->input('seller_response'),
                'seller_responded_at' => now(),
            ]);

            // Add initial shipping history milestone
            $returnRequest->addShippingMilestone(
                'approved',
                'Return Authorized by Seller',
                $warehouseAddress,
                'Seller has approved return. Please ship item back within policy window.'
            );

            // Notify buyer (SEL-RET-002 / A23)
            if (Schema::hasTable('buyer_notifications')) {
                BuyerNotification::create([
                    'user_id' => $returnRequest->buyer_id,
                    'title' => 'Return Request Approved',
                    'message' => "Seller approved your return for Order #{$order->order_number}. Please ship item back to: {$warehouseAddress}.",
                ]);
            }

            return back()->with('status', 'Return request approved. Buyer has been notified with return instructions.');
        }

        // Rejection validation (SEL-RET-003 / A25: Reason needs 10-300 characters)
        $rawReason = trim((string) ($request->input('reason') ?? $request->input('rejection_reason') ?? $request->input('seller_response') ?? ''));
        $reasonLength = mb_strlen($rawReason);

        if ($rawReason === '' || $reasonLength < 10 || $reasonLength > 300) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rejection_reason' => 'The rejection reason must be between 10 and 300 characters.',
                'reason' => 'The rejection reason must be between 10 and 300 characters.',
                'seller_response' => 'The rejection reason must be between 10 and 300 characters.',
            ]);
        }

        // Rejection with reason (SEL-RET-004)
        $returnRequest->update([
            'decision' => 'rejected',
            'status' => 'rejected',
            'rejection_reason' => $rawReason,
            'seller_response' => $rawReason,
            'seller_responded_at' => now(),
            'resolved_at' => now(),
        ]);

        // Notify buyer (SEL-RET-004 / A23)
        if (Schema::hasTable('buyer_notifications')) {
            BuyerNotification::create([
                'user_id' => $returnRequest->buyer_id,
                'title' => 'Return Request Rejected',
                'message' => "Your return request for Order #{$order->order_number} was rejected. Reason: {$rawReason}.",
            ]);
        }

        return back()->with('status', 'Return request rejected. Rejection reason was communicated to the buyer.');
    }

    /**
     * Track return shipping carrier milestones & waybill (SELLER-39).
     */
    public function updateTracking(Request $request, OrderReturnRequest $returnRequest): RedirectResponse
    {
        abort_unless((int) $returnRequest->seller_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'return_carrier' => 'required|string|max:100',
            'return_tracking_number' => 'required|string|max:100',
            'return_waybill_url' => 'nullable|string|max:2048',
            'return_shipping_status' => 'required|in:pending_shipment,in_transit,out_for_delivery,delivered',
            'milestone_title' => 'nullable|string|max:150',
            'milestone_location' => 'nullable|string|max:150',
            'milestone_notes' => 'nullable|string|max:500',
        ]);

        $statusTitles = [
            'pending_shipment' => 'Waybill Generated & Awaiting Carrier Pickup',
            'in_transit' => 'Return Parcel In Transit',
            'out_for_delivery' => 'Out for Delivery to Seller Warehouse',
            'delivered' => 'Delivered to Seller Warehouse',
        ];

        $title = ($validated['milestone_title'] ?? null) ?: ($statusTitles[$validated['return_shipping_status']] ?? 'Shipping Milestone');
        $location = ($validated['milestone_location'] ?? null) ?: ($validated['return_carrier'] . ' Logistics Facility');
        $notes = ($validated['milestone_notes'] ?? null) ?: "Carrier: {$validated['return_carrier']} | Tracking: {$validated['return_tracking_number']}";

        $dataToUpdate = [
            'return_carrier' => $validated['return_carrier'],
            'return_tracking_number' => $validated['return_tracking_number'],
            'return_waybill_url' => $validated['return_waybill_url'] ?? $returnRequest->return_waybill_url,
            'return_shipping_status' => $validated['return_shipping_status'],
        ];

        if (!$returnRequest->buyer_shipped_at && in_array($validated['return_shipping_status'], ['in_transit', 'out_for_delivery', 'delivered'])) {
            $dataToUpdate['buyer_shipped_at'] = now();
        }

        // If marked delivered, advance request status to item_returned (SELLER-37 / SELLER-40)
        if ($validated['return_shipping_status'] === 'delivered') {
            $dataToUpdate['item_received_at'] = now();
            if ($returnRequest->status === 'approved') {
                $dataToUpdate['status'] = 'item_returned';
            }
        }

        $returnRequest->update($dataToUpdate);
        $returnRequest->addShippingMilestone(
            $validated['return_shipping_status'],
            $title,
            $location,
            $notes
        );

        // Notify buyer of delivery milestone
        if ($validated['return_shipping_status'] === 'delivered' && Schema::hasTable('buyer_notifications')) {
            BuyerNotification::create([
                'user_id' => $returnRequest->buyer_id,
                'title' => 'Return Parcel Received by Seller',
                'message' => "Your returned parcel for Order #{$returnRequest->order->order_number} has been delivered to the seller and is undergoing inspection.",
            ]);
        }

        return back()->with('status', 'Return shipping milestone and tracking updated successfully.');
    }

    /**
     * Inspect returned item & resolve refund (SELLER-40, SELLER-41).
     */
    public function inspectAndResolve(Request $request, OrderReturnRequest $returnRequest): RedirectResponse
    {
        abort_unless((int) $returnRequest->seller_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'inspection_status' => 'required|in:passed,failed,partial_damage',
            'inspection_notes' => 'nullable|string|max:2000',
            'refund_amount' => 'required_if:inspection_status,passed,partial_damage|nullable|numeric|min:0|max:' . ($returnRequest->requested_amount ?: 999999),
            'refund_method' => 'required_if:inspection_status,passed,partial_damage|nullable|in:original_payment,store_credit,manual_transfer,wallet',
            'restock_inventory' => 'nullable|boolean',
        ]);

        $order = $returnRequest->order;
        $orderItem = $returnRequest->orderItem;
        $inspectionStatus = $validated['inspection_status'];

        if ($inspectionStatus === 'failed') {
            // Inspection failed: reject refund
            $returnRequest->update([
                'inspection_status' => 'failed',
                'inspection_notes' => $validated['inspection_notes'] ?? 'Returned item did not pass quality inspection.',
                'status' => 'rejected',
                'resolved_at' => now(),
            ]);

            if (Schema::hasTable('buyer_notifications')) {
                BuyerNotification::create([
                    'user_id' => $returnRequest->buyer_id,
                    'title' => 'Return Inspection Failed',
                    'message' => "Returned item for Order #{$order->order_number} failed inspection. Notes: " . ($validated['inspection_notes'] ?? 'Item damaged or missing accessories.'),
                ]);
            }

            return back()->with('status', 'Inspection completed: Item failed inspection. Refund was denied.');
        }

        // Passed or Partial: Issue refund & optionally restock
        DB::transaction(function () use ($returnRequest, $validated, $order, $orderItem, $request, $inspectionStatus) {
            $refundAmount = (float) ($validated['refund_amount'] ?? $returnRequest->requested_amount);
            $restocked = false;

            // Restock inventory if seller opted in (SELLER-40)
            if ($request->boolean('restock_inventory') && $orderItem) {
                $qty = (int) $orderItem->quantity;

                if ($orderItem->product_variant_id) {
                    $variant = ProductVariant::find($orderItem->product_variant_id);
                    if ($variant) {
                        $beforeStock = $variant->stock;
                        $variant->increment('stock', $qty);
                        $afterStock = $variant->stock;

                        if (Schema::hasTable('stock_adjustments')) {
                            StockAdjustment::create([
                                'seller_id' => $returnRequest->seller_id,
                                'user_id' => $request->user()->id,
                                'product_id' => $orderItem->product_id,
                                'product_variant_id' => $variant->id,
                                'type' => 'return',
                                'quantity' => $qty,
                                'before_stock' => $beforeStock,
                                'after_stock' => $afterStock,
                                'notes' => "Restocked from Return #{$returnRequest->id} (Order #{$order->order_number})",
                                'reference_number' => "RET-{$returnRequest->id}",
                            ]);
                        }
                    }
                }

                if ($orderItem->product_id) {
                    $product = Product::find($orderItem->product_id);
                    if ($product) {
                        $beforeStock = $product->stock;
                        $product->increment('stock', $qty);
                        $afterStock = $product->stock;

                        if (!$orderItem->product_variant_id && Schema::hasTable('stock_adjustments')) {
                            StockAdjustment::create([
                                'seller_id' => $returnRequest->seller_id,
                                'user_id' => $request->user()->id,
                                'product_id' => $product->id,
                                'type' => 'return',
                                'quantity' => $qty,
                                'before_stock' => $beforeStock,
                                'after_stock' => $afterStock,
                                'notes' => "Restocked from Return #{$returnRequest->id} (Order #{$order->order_number})",
                                'reference_number' => "RET-{$returnRequest->id}",
                            ]);
                        }
                    }
                }

                $restocked = true;
            }

            // Update Return Request status
            $returnRequest->update([
                'inspection_status' => $inspectionStatus,
                'inspection_notes' => $validated['inspection_notes'] ?? null,
                'refunded_amount' => $refundAmount,
                'refund_method' => $validated['refund_method'] ?? 'original_payment',
                'restocked' => $restocked,
                'status' => 'refunded',
                'resolved_at' => now(),
            ]);

            // SELLER-41: Maintain itemized ledger of issued refund
            RefundLedger::create([
                'refund_reference' => 'REF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'order_return_request_id' => $returnRequest->id,
                'order_id' => $returnRequest->order_id,
                'order_item_id' => $returnRequest->order_item_id,
                'seller_id' => $returnRequest->seller_id,
                'buyer_id' => $returnRequest->buyer_id,
                'original_amount' => $returnRequest->requested_amount,
                'refunded_amount' => $refundAmount,
                'refund_type' => $returnRequest->type,
                'refund_method' => $validated['refund_method'] ?? 'original_payment',
                'status' => 'completed',
                'reason' => $returnRequest->reason,
                'notes' => $validated['inspection_notes'] ?? "Inspection: {$inspectionStatus}. Refund completed.",
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            if ($orderItem) {
                $orderItem->update(['status' => 'refunded']);
            }
        });

        // Notify Buyer
        if (Schema::hasTable('buyer_notifications')) {
            $formattedAmount = number_format((float) ($validated['refund_amount'] ?? $returnRequest->requested_amount), 2);
            BuyerNotification::create([
                'user_id' => $returnRequest->buyer_id,
                'title' => 'Refund Issued',
                'message' => "Inspection passed for Order #{$order->order_number}. A refund of ₱{$formattedAmount} has been processed via {$validated['refund_method']}.",
            ]);
        }

        return back()->with('status', 'Item inspection confirmed and refund successfully recorded in the ledger.');
    }
}
