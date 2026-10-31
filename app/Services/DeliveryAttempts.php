<?php

namespace App\Services;

use App\Models\{BuyerNotification, DeliveryProof, LogisticsAuditEvent, Parcel, ParcelDeliveryAttempt, ParcelReturn, SellerNotification, User};
use Illuminate\Support\Facades\{DB, Schema};

class DeliveryAttempts
{
    public const REASONS = ['Buyer not home', 'Wrong address', 'Refused'];
    public function requireStorage(): void
    {
        app(LogisticsAudit::class)->requireStorage();
        foreach (['parcel_delivery_attempts', 'parcel_returns'] as $table) abort_unless(Schema::hasTable($table), 503, 'Delivery attempt and return tracking is temporarily unavailable.');
    }
    public function state(Parcel $parcel): array
    {
        if (!Schema::hasTable('parcel_delivery_attempts') || !Schema::hasTable('parcel_returns') || !Schema::hasTable('logistics_audit_events')) return ['attempt_count' => 0, 'awaiting_retry' => false, 'rts_eligible' => false];
        $count = (int) ParcelDeliveryAttempt::where('parcel_id', $parcel->id)->max('attempt_number');
        $retry = $count > 0 && LogisticsAuditEvent::where('parcel_id', $parcel->id)->where('action', 'delivery_retry_started')->where('data->attempt_number', $count + 1)->exists();
        $return = ParcelReturn::where('parcel_id', $parcel->id)->first();
        return ['attempt_count' => $count, 'awaiting_retry' => $count > 0 && $count < 3 && !$retry && !$return,
            'rts_eligible' => $count >= 3 || $return !== null, 'return_status' => $return?->status];
    }
    public function assertCanComplete(Parcel $parcel): void
    {
        $state = $this->state($parcel);
        abort_if(($state['return_status'] ?? null) !== null || $state['rts_eligible'], 422, 'This parcel is in Return to Seller tracking and cannot be completed as Buyer delivery.');
        abort_if($state['awaiting_retry'], 422, 'Start the next delivery attempt before recording a successful delivery.');
    }
    public function proofAllowed(Parcel $parcel, DeliveryProof $proof): bool
    {
        if (!Schema::hasTable('logistics_audit_events')) return true;
        $retry = LogisticsAuditEvent::where('parcel_id', $parcel->id)->where('action', 'delivery_retry_started')->latest('id')->first();
        return !$retry || !in_array($proof->id, $retry->data['previous_proof_ids'] ?? [], true);
    }
    private function owned(User $actor, int $id): Parcel
    {
        app(SellerShipmentFinance::class)->lockParcel($id);
        $parcel = Parcel::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->usertype === 'rider' && (int) $parcel->assigned_rider_id === (int) $actor->rider?->id, 403, 'This delivery is not assigned to you.');
        abort_unless($parcel->status === 'OUT_FOR_DELIVERY', 422, 'Only a delivery currently in progress can record an attempt.');
        return $parcel;
    }
    public function fail(User $actor, int $id, string $reason): Parcel
    {
        if (!in_array($reason, self::REASONS, true)) throw \Illuminate\Validation\ValidationException::withMessages(['reason' => 'Choose Buyer not home, Wrong address, or Refused.']);
        $this->requireStorage();
        return DB::transaction(function () use ($actor, $id, $reason) {
            $parcel = $this->owned($actor, $id); $state = $this->state($parcel);
            abort_if($state['rts_eligible'] || ($state['return_status'] ?? null), 422, 'This parcel is already eligible for Return to Seller.');
            abort_if($state['awaiting_retry'], 409, 'This failed attempt is already recorded. Start a new attempt before recording another outcome.');
            $number = $state['attempt_count'] + 1;
            $attempt = ParcelDeliveryAttempt::create(['parcel_id' => $id, 'rider_id' => $actor->rider->id, 'attempt_number' => $number,
                'reason' => $reason, 'created_at' => now()]);
            $parcel->update(['failure_reason' => $reason]);
            if ($parcel->orderItem) $parcel->orderItem->statusHistories()->create(['status' => $parcel->orderItem->status,
                'note' => 'Delivery attempt '.$number.' failed: '.$reason]);
            app(LogisticsAudit::class)->record($actor, 'delivery_attempt_failed', $parcel, ['attempt_id' => $attempt->id, 'attempt_number' => $number, 'reason' => $reason], $actor->rider->id);
            if (($number === 3 || $reason === 'Refused') && $parcel->sorting_center_id) ParcelReturn::firstOrCreate(['parcel_id' => $id], ['sorting_center_id' => $parcel->sorting_center_id,
                'status' => 'eligible', 'reason' => $reason === 'Refused' ? 'Buyer refused delivery.' : 'Three failed delivery attempts. Latest reason: '.$reason]);
            if ($parcel->buyer_id) BuyerNotification::create(['user_id' => $parcel->buyer_id, 'type' => 'delivery_attempt',
                'title' => 'Delivery attempt unsuccessful', 'message' => 'Parcel '.$parcel->order_id.', attempt '.$number.': '.$reason]);
            if ($seller = app(SellerParcelDelivery::class)->verifiedSellerId($parcel)) SellerNotification::create(['user_id' => $seller,
                'type' => 'delivery_attempt', 'title' => 'Delivery attempt unsuccessful', 'message' => 'Parcel '.$parcel->order_id.', attempt '.$number.': '.$reason,
                'url' => route('seller.order.details', ['orderItem' => $parcel->order_item_id], false)]);
            return $parcel->fresh();
        }, 5);
    }
    public function retry(User $actor, int $id): Parcel
    {
        $this->requireStorage();
        return DB::transaction(function () use ($actor, $id) {
            $parcel = $this->owned($actor, $id); $state = $this->state($parcel);
            abort_unless($state['awaiting_retry'], 409, 'There is no failed attempt awaiting an authorized retry.');
            app(LogisticsAudit::class)->record($actor, 'delivery_retry_started', $parcel, ['attempt_number' => $state['attempt_count'] + 1,
                'previous_proof_ids' => $parcel->deliveryProofs()->pluck('id')->all()], $actor->rider->id);
            return $parcel->fresh();
        }, 5);
    }
}
