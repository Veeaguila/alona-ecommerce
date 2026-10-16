<?php

namespace App\Observers;

use App\Models\{Parcel, PickupRequest, Rider, RiderDeliveryEarning, RiderNotification, SortingCenterMessage, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Log};

/** Snapshot event facts before later saves; publish only after the outer transaction commits. */
class RiderNotificationObserver
{
    public function created(Model $model): void { $this->capture($model, true); }
    public function updated(Model $model): void { $this->capture($model, false); }

    private function later(callable $publish): void
    {
        DB::afterCommit(function () use ($publish): void {
            try {
                $publish();
            } catch (\Throwable $failure) {
                // Never report an already committed delivery or payout as failed.
                Log::warning('Rider notification could not be recorded.', ['exception' => get_class($failure)]);
            }
        });
    }

    private function record(int $userId, string $key, string $type, string $title, string $body, array $data): void
    {
        RiderNotification::firstOrCreate(['user_id' => $userId, 'event_key' => hash('sha256', $key)], compact('type', 'title', 'body', 'data'));
    }

    private function rider(?int $id, string $key, string $type, string $title, string $body, array $data): void
    {
        $userId = $id ? Rider::whereKey($id)->value('user_id') : null;
        if ($userId) { $this->record($userId, $key, $type, $title, $body, $data); }
    }

    private function capture(Model $model, bool $created): void
    {
        $id = $model->getKey();
        // Laravel can disable timestamps/casts during remember-token updates on logout.
        $updatedAt = $model->updated_at;
        $stamp = $updatedAt ? \Illuminate\Support\Carbon::parse($updatedAt)->format('Y-m-d H:i:s.u') : '';
        if ($model instanceof PickupRequest && $model->status === 'confirmed' && ($created || $model->wasChanged('status'))) {
            $parcelId = $model->parcel_id;
            $this->later(function () use ($id, $parcelId): void {
                $parcel = Parcel::find($parcelId);
                if (!$parcel || !$parcel->sorting_center_id || $parcel->pickup_rider_id || !in_array($parcel->status, ['PLACED', 'PREPARING'], true)) { return; }
                Rider::where('sorting_center_id', $parcel->sorting_center_id)->operationallyEligible()->chunkById(100, function ($riders) use ($id, $parcelId): void {
                    foreach ($riders as $rider) {
                        $this->record($rider->user_id, "pickup:$id:confirmed", 'pickup_available', 'New pickup request',
                            'A confirmed pickup is available at your sorting center.', ['screen' => 'pickups', 'parcel_id' => $parcelId]);
                    }
                });
            });
        }
        if ($model instanceof Parcel) {
            foreach (['pickup_rider_id' => 'pickups', 'assigned_rider_id' => 'deliveries'] as $field => $screen) {
                if (!$model->$field || !($created || $model->wasChanged($field))) { continue; }
                $riderId = $model->$field;
                $title = $screen === 'pickups' ? 'Pickup accepted' : ($model->status === 'AT_SORTING_CENTER' ? 'Delivery assigned — ready for collection' : 'Delivery assigned');
                $this->later(fn () => $this->rider($riderId, "parcel:$id:$field:$riderId:$stamp", 'assignment', $title,
                    'Open your assignments for the current parcel details.', ['screen' => $screen, 'parcel_id' => $id]));
            }
            if (!$created && $model->wasChanged('status')) {
                $status = $model->status;
                $pickup = $model->pickup_rider_id;
                $delivery = $model->assigned_rider_id;
                $this->later(function () use ($id, $stamp, $status, $pickup, $delivery): void {
                    foreach (array_unique(array_filter([$pickup, $delivery])) as $riderId) {
                        $screen = in_array($status, ['DELIVERED', 'FAILED_RETURNED'], true) ? 'history' : ($riderId === $delivery ? 'deliveries' : 'pickups');
                        $this->rider($riderId, "parcel:$id:status:$status:$stamp", 'parcel_status', 'Parcel status updated',
                            'Parcel '.$id.': '.str_replace('_', ' ', $status), ['screen' => $screen, 'parcel_id' => $id]);
                    }
                });
            }
        }
        if (($model instanceof User && $model->usertype === 'rider' && !$created && $model->wasChanged(['status', 'rejection_reason', 'email_verified_at'])) ||
            ($model instanceof Rider && !$created && $model->wasChanged(['approval_status', 'is_active']))) {
            $userId = $model instanceof User ? $id : $model->user_id;
            $this->later(function () use ($userId): void {
                $user = User::with('rider')->find($userId);
                if (!$user?->rider) { return; }
                $state = [$user->status, $user->rider->approval_status, (bool) $user->rider->is_active, (bool) $user->email_verified_at];
                $key = 'account:'.$userId.':'.json_encode($state).':'.max((string) $user->updated_at, (string) $user->rider->updated_at);
                $this->record($userId, $key, 'account', 'Account status updated',
                    'Account: '.$user->status.'. Rider approval: '.$user->rider->approval_status.'. Open My account for details.', ['screen' => 'account']);
            });
        }
        if ($model instanceof SortingCenterMessage && $created && $model->conversation_id && $model->recipient_id) {
            $userId = $model->recipient_id;
            $conversationId = $model->conversation_id;
            $this->later(function () use ($id, $userId, $conversationId): void {
                if (User::whereKey($userId)->where('usertype', 'rider')->exists()) {
                    $this->record($userId, "message:$id", 'message', 'New Logistics message',
                        'Open Messages to read the conversation.', ['screen' => 'chat', 'conversation_id' => $conversationId]);
                }
            });
        }
        if ($model instanceof RiderDeliveryEarning && ($created || $model->wasChanged('payout_status'))) {
            $riderId = $model->rider_id;
            $paid = $model->payout_status === 'paid';
            $amount = $model->amount;
            $this->later(fn () => $this->rider($riderId, "earning:$id:".($paid ? 'paid' : 'earned'), 'earnings',
                $paid ? 'Payout recorded' : 'Delivery earnings recorded', 'PHP '.$amount.($paid ? ' marked paid.' : ' earned. Payout is pending.'), ['screen' => 'earnings']));
        }
    }
}
