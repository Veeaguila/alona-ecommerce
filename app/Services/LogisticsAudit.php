<?php

namespace App\Services;

use App\Models\{LogisticsAuditEvent, Parcel, User};
use Illuminate\Support\Facades\Schema;

class LogisticsAudit
{
    public function requireStorage(): void
    {
        abort_unless(Schema::hasTable('logistics_audit_events'), 503, 'This workflow is temporarily unavailable. Please contact your administrator.');
    }
    public function record(User $actor, string $action, ?Parcel $parcel = null, array $data = [], ?int $riderId = null, ?int $centerId = null): LogisticsAuditEvent
    {
        $this->requireStorage();
        return LogisticsAuditEvent::create(['actor_id' => $actor->id, 'action' => $action, 'parcel_id' => $parcel?->id,
            'rider_id' => $riderId, 'sorting_center_id' => $centerId ?? $parcel?->sorting_center_id,
            'data' => $data, 'created_at' => now()]);
    }
}
