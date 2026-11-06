<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{BuyerNotification, Commission, LogisticsAuditEvent, Order, OrderReturnRequest, Parcel, ParcelIncident, RefundLedger, SellerNotification};
use App\Services\{LogisticsAudit, SellerParcelDelivery, SellerShipmentFinance};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;

class ParcelOperationsController extends Controller
{
    private function owned(Request $request, int $id, bool $lock = false): Parcel
    {
        $query = Parcel::where('sorting_center_id', $request->user()->sortingCenter->id)->whereKey($id);
        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function show(Request $request, int $id)
    {
        $parcel = $this->owned($request, $id);
        app(LogisticsAudit::class)->requireStorage();
        $events = LogisticsAuditEvent::where('parcel_id', $id)->with('actor:id,name')->orderBy('id')->get();
        $incidents = Schema::hasTable('parcel_incidents') ? ParcelIncident::where('parcel_id', $id)->orderBy('id')->get() : collect();
        return response()->json(['data' => ['parcel' => $parcel, 'events' => $events, 'incidents' => $incidents,
            'review' => app(\App\Services\LogisticsRefundReview::class)->details($parcel)]]);
    }

    public function overrideDelivered(Request $request, int $id)
    {
        $input = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:300']]);
        $this->owned($request, $id);
        app(LogisticsAudit::class)->requireStorage();
        $parcel = DB::transaction(function () use ($request, $id, $input) {
            $this->lockOwnedOrder($request, $id);
            app(SellerShipmentFinance::class)->lockParcel($id);
            $parcel = $this->owned($request, $id, true);
            abort_unless($parcel->status === 'OUT_FOR_DELIVERY', 422, 'Only an out-for-delivery parcel can be overridden as Delivered.');
            app(\App\Services\DeliveryAttempts::class)->assertCanComplete($parcel);
            $previous = $parcel->status;
            $parcel->update(['status' => 'DELIVERED', 'delivered_at' => now()]);
            app(LogisticsAudit::class)->record($request->user(), 'status_override', $parcel, [
                'previous_status' => $previous, 'new_status' => 'DELIVERED', 'reason' => $input['reason'],
                'privileged_proof_exception' => true, 'financial_recognition' => false,
            ]);
            $this->notify($parcel, 'Logistics delivery confirmation', $input['reason'], 'delivery_override');
            // This exception confirms delivery, not fee eligibility, cash receipt, or a payout.
            return $parcel->fresh();
        }, 5);
        return response()->json(['message' => 'Delivered override recorded with an immutable audit.', 'data' => $parcel]);
    }

    public function incident(Request $request, int $id)
    {
        $input = $request->validate([
            'request_key' => ['required', 'uuid'], 'type' => ['required', 'in:lost,damaged'],
            'reason' => ['required', 'string', 'min:10', 'max:300'],
            'file' => ['required_if:type,damaged', 'nullable', 'file', 'image', 'max:20480', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $this->owned($request, $id);
        app(LogisticsAudit::class)->requireStorage();
        abort_unless(Schema::hasTable('parcel_incidents'), 503, 'Incident reporting is temporarily unavailable.');
        $path = null;
        try {
            $incident = DB::transaction(function () use ($request, $id, $input, &$path) {
                $this->lockOwnedOrder($request, $id);
                app(SellerShipmentFinance::class)->lockParcel($id);
                $parcel = $this->owned($request, $id, true);
                $existing = ParcelIncident::where('request_key', $input['request_key'])->first();
                if ($existing) {
                    abort_unless((int) $existing->parcel_id === $id && (int) $existing->reported_by === (int) $request->user()->id
                        && $existing->type === $input['type'] && $existing->reason === $input['reason'], 409, 'This request key has already been used.');
                    return $existing;
                }
                abort_unless(in_array($parcel->status, ['PICKED_UP', 'AT_SORTING_CENTER', 'IN_HUB_TRANSIT', 'OUT_FOR_DELIVERY'], true), 422,
                    'Only parcels currently in the delivery network can be reported lost or damaged.');
                if ($request->hasFile('file') && !$path) {
                    $path = $request->file('file')->store('parcel-incidents', 'local');
                    abort_unless($path, 500, 'Incident evidence could not be saved.');
                }
                $incident = ParcelIncident::create([
                    'request_key' => $input['request_key'], 'parcel_id' => $id, 'sorting_center_id' => $parcel->sorting_center_id,
                    'reported_by' => $request->user()->id, 'type' => $input['type'], 'reason' => $input['reason'],
                    'previous_status' => $parcel->status, 'evidence_path' => $path,
                ]);
                $parcel->update(['status' => strtoupper($input['type'])]);
                app(LogisticsAudit::class)->record($request->user(), 'incident_reported', $parcel, [
                    'incident_id' => $incident->id, 'type' => $incident->type, 'previous_status' => $incident->previous_status,
                    'new_status' => $parcel->status, 'reason' => $incident->reason, 'has_evidence' => $path !== null,
                    'refund_review' => 'pending_authorized_workflow',
                ]);
                if ($parcel->orderItem) $parcel->orderItem->statusHistories()->create([
                    'status' => $parcel->orderItem->status, 'note' => 'Logistics reported '.$incident->type.': '.$incident->reason,
                ]);
                $this->notify($parcel, 'Parcel reported '.$incident->type, $incident->reason, 'parcel_incident');
                if ($incident->type === 'lost') app(\App\Services\LogisticsRefundReview::class)->openClaim($request->user(), $parcel, $incident);
                return $incident;
            }, 5);
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($path && $incident->evidence_path !== $path) Storage::disk('local')->delete($path);
        return response()->json(['message' => 'Incident recorded. Refund review requires the existing authorized workflow.', 'data' => $incident], 201);
    }

    public function executeRefund(Request $request, int $id)
    {
        $this->owned($request, $id);
        app(LogisticsAudit::class)->requireStorage();

        $result = DB::transaction(function () use ($request, $id) {
            $this->lockOwnedOrder($request, $id);
            app(SellerShipmentFinance::class)->lockParcel($id);
            $parcel = $this->owned($request, $id, true);

            $event = LogisticsAuditEvent::where('parcel_id', $parcel->id)
                ->where('action', 'logistics_refund_requested')
                ->oldest('id')
                ->first();

            abort_unless($event && !empty($event->data['return_request_id']), 422, 'No pending logistics refund review found for this parcel.');

            $returnRequest = OrderReturnRequest::whereKey($event->data['return_request_id'])->lockForUpdate()->firstOrFail();
            abort_unless($returnRequest->status === 'pending', 422, 'This refund review is already resolved or cancelled.');

            $order = Order::withoutEvents(fn () => Order::whereKey($returnRequest->order_id)->lockForUpdate()->firstOrFail());
            abort_unless($order->payment_status === 'paid', 422, 'Only paid orders can have an executed refund.');

            $amount = (float) $returnRequest->requested_amount;
            abort_unless($amount > 0, 422, 'The refund amount must be greater than zero.');

            $refundMethod = $order->payment_method ?? 'gcash';

            $refundLedger = RefundLedger::create([
                'refund_reference' => 'REF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'order_return_request_id' => $returnRequest->id,
                'order_id' => $returnRequest->order_id,
                'order_item_id' => $returnRequest->order_item_id,
                'seller_id' => $returnRequest->seller_id,
                'buyer_id' => $returnRequest->buyer_id,
                'original_amount' => $returnRequest->requested_amount,
                'refunded_amount' => $amount,
                'refund_type' => 'refund_only',
                'refund_method' => $refundMethod,
                'status' => 'completed',
                'reason' => 'Logistics RTS refund approved',
                'notes' => 'Authorized refund executed by Logistics sorting center for returned parcel.',
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            $returnRequest->update([
                'status' => 'refunded',
                'refunded_amount' => $amount,
                'refund_method' => $refundMethod,
                'resolved_at' => now(),
            ]);

            $order->update([
                'refund_status' => 'refunded',
                'refund_amount' => $amount,
                'payment_status' => 'refunded',
            ]);

            if ($parcel->orderItem) {
                $parcel->orderItem->update(['status' => 'refunded']);
            }

            if (Schema::hasTable('commissions')) {
                Commission::where('order_id', $order->id)->update(['status' => 'cancelled']);
            }

            app(LogisticsAudit::class)->record($request->user(), 'logistics_refund_executed', $parcel, [
                'return_request_id' => $returnRequest->id,
                'refund_ledger_id' => $refundLedger->id,
                'refund_reference' => $refundLedger->refund_reference,
                'amount' => $amount,
                'method' => $refundMethod,
            ]);

            if ($order->user_id && Schema::hasTable('buyer_notifications')) {
                BuyerNotification::create([
                    'user_id' => $order->user_id,
                    'type' => 'logistics_refund_executed',
                    'title' => 'Refund Processed',
                    'message' => "A refund of ₱" . number_format($amount, 2) . " for Order #{$order->order_number} has been issued to your {$refundMethod} account.",
                ]);
            }

            $sellerId = app(SellerParcelDelivery::class)->verifiedSellerId($parcel);
            if ($sellerId && Schema::hasTable('seller_notifications')) {
                SellerNotification::create([
                    'user_id' => $sellerId,
                    'type' => 'logistics_refund_executed',
                    'title' => 'RTS Refund Completed',
                    'message' => "Logistics completed the refund for Order #{$order->order_number} following confirmed item restock.",
                    'url' => route('seller.returns', [], false),
                ]);
            }

            return ['refund' => $returnRequest->fresh(), 'ledger' => $refundLedger];
        }, 5);

        return response()->json([
            'message' => 'Refund of ₱' . number_format($result['refund']->refunded_amount, 2) . ' authorized and successfully issued.',
            'data' => [
                'review' => app(\App\Services\LogisticsRefundReview::class)->details($this->owned($request, $id)),
            ],
        ]);
    }

    public function evidence(Request $request, int $id, int $incidentId)
    {
        $this->owned($request, $id);
        $incident = ParcelIncident::where('parcel_id', $id)->findOrFail($incidentId);
        abort_unless($incident->evidence_path && Storage::disk('local')->exists($incident->evidence_path), 404);
        return Storage::disk('local')->response($incident->evidence_path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function notify(Parcel $parcel, string $title, string $reason, string $type): void
    {
        if ($parcel->buyer_id) BuyerNotification::create(['user_id' => $parcel->buyer_id, 'title' => $title,
            'message' => 'Parcel '.$parcel->order_id.': '.$reason, 'type' => $type]);
        $sellerId = app(SellerParcelDelivery::class)->verifiedSellerId($parcel);
        if ($sellerId) SellerNotification::create(['user_id' => $sellerId, 'type' => $type, 'title' => $title,
            'message' => 'Parcel '.$parcel->order_id.': '.$reason,
            'url' => route('seller.order.details', ['orderItem' => $parcel->order_item_id], false)]);
    }

    private function lockOwnedOrder(Request $request, int $id): void
    {
        $orderId = $this->owned($request, $id)->orderItem?->order_id;
        if ($orderId) \App\Models\Order::withoutEvents(fn () => \App\Models\Order::whereKey($orderId)->lockForUpdate()->firstOrFail());
    }
}
