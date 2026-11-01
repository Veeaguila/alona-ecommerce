<?php

namespace App\Services;

use App\Models\{BuyerNotification, LogisticsAuditEvent, Order, OrderReturnRequest, Parcel, ParcelIncident, RefundLedger, SellerNotification, User};
use Illuminate\Support\Facades\Schema;

class LogisticsRefundReview
{
    public const REASON = 'Logistics refund review';

    // These requests start review only. They never call payment, stock, commission or payout execution.
    public function openClaim(User $actor, Parcel $parcel, ParcelIncident $incident): void
    {
        app(LogisticsAudit::class)->record($actor, 'logistics_claim_opened', $parcel, [
            'incident_id' => $incident->id, 'reference' => 'INC-'.$incident->id, 'state' => 'pending_review',
            'reason' => $incident->reason, 'financial_amount' => null,
        ]);
        $this->request($actor, $parcel, 'lost');
    }

    public function request(User $actor, Parcel $parcel, string $cause): ?OrderReturnRequest
    {
        $item = $parcel->orderItem;
        $sellerId = Order::withoutEvents(fn () => app(SellerParcelDelivery::class)->verifiedSellerId($parcel));
        if (!$item || !$sellerId) return null;
        // Verification must not invoke checkout's unrelated unpaid-order cleanup on a model read.
        $order = Order::withoutEvents(fn () => Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail());
        if (in_array($order->payment_method, ['cod', 'cash_on_delivery'], true) || $order->payment_status !== 'paid') return null;
        if (!Schema::hasTable('order_return_requests') || !Schema::hasTable('refund_ledgers')) return null;
        $blocked = $order->items()->count() !== 1 ? 'Combined-order refund allocation requires an authorized decision.'
            : (RefundLedger::where('order_id', $order->id)->exists() ? 'Existing refund history requires reconciliation before another request.' : null);
        if ($blocked) {
            app(LogisticsAudit::class)->record($actor, 'logistics_refund_review_blocked', $parcel, ['cause' => $cause, 'reason' => $blocked]);
            return null;
        }
        $existing = OrderReturnRequest::where('order_item_id', $item->id)->whereNotIn('status', ['cancelled', 'rejected'])->first();
        if ($existing) return $existing;
        $amount = (string) $order->total;
        if (!preg_match('/\A[0-9]+\.[0-9]{2}\z/', $amount) || app(CodLedger::class)->cents($amount) <= 0) return null;
        $receivedAt = $cause === 'rts' ? $parcel->returnToSeller?->seller_received_at : null;
        $request = OrderReturnRequest::create(['order_id' => $order->id, 'order_item_id' => $item->id,
            'buyer_id' => $order->user_id, 'seller_id' => $sellerId, 'type' => 'refund_only', 'reason' => self::REASON,
            'reason_details' => 'Logistics initiated '.$cause.' review. Requested amount is the recorded paid total for the entire single-item order. Refund execution is blocked pending a verified authorized workflow.',
            'requested_amount' => $amount, 'status' => 'pending', 'evidence_images' => [], 'item_received_at' => $receivedAt,
            'restocked' => $receivedAt !== null]);
        app(LogisticsAudit::class)->record($actor, 'logistics_refund_requested', $parcel, ['return_request_id' => $request->id,
            'cause' => $cause, 'requested_amount' => $request->requested_amount, 'amount_source' => 'recorded paid single-item order total',
            'execution_state' => 'blocked_pending_verified_workflow']);
        BuyerNotification::create(['user_id' => $order->user_id, 'type' => 'logistics_refund_review', 'title' => 'Refund review started',
            'message' => 'A refund review for order '.$order->order_number.' was opened using its recorded paid amount. No refund has been issued.']);
        SellerNotification::create(['user_id' => $sellerId, 'type' => 'logistics_refund_review', 'title' => 'Logistics refund review pending',
            'message' => 'Logistics opened a refund review for order '.$order->order_number.'. Execution remains blocked; no payout or stock adjustment is performed by this request.',
            'url' => route('seller.returns', [], false)]);
        return $request;
    }

    public function isManaged(int $requestId): bool
    {
        return Schema::hasTable('logistics_audit_events') && LogisticsAuditEvent::where('action', 'logistics_refund_requested')->where('data->return_request_id', $requestId)->exists();
    }
    public function prohibitExecution(OrderReturnRequest $request): void
    {
        abort_if($this->isManaged($request->id), 422,
            'Logistics refund review is pending. Refund execution is blocked until the authorized payment workflow is verified and approved. No stock or payout changes were made.');
    }
    public function details(Parcel $parcel): array
    {
        if (!Schema::hasTable('logistics_audit_events')) return ['claim' => null, 'refund' => null];
        $claim = LogisticsAuditEvent::where('parcel_id', $parcel->id)->where('action', 'logistics_claim_opened')->oldest('id')->first();
        $event = LogisticsAuditEvent::where('parcel_id', $parcel->id)->where('action', 'logistics_refund_requested')->oldest('id')->first();
        $request = $event ? OrderReturnRequest::find($event->data['return_request_id']) : null;
        return ['claim' => $claim ? ['reference' => $claim->data['reference'], 'state' => 'pending_review', 'opened_at' => $claim->created_at] : null,
            'refund' => $request ? ['id' => $request->id, 'status' => $request->status, 'requested_amount' => $request->requested_amount,
                'execution_blocked' => true, 'created_at' => $request->created_at] : null];
    }
}
