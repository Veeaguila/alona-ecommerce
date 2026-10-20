<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'changes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Record an audit action.
     */
    public static function record(
        int|User $admin,
        string $action,
        string $description,
        ?Model $target = null,
        ?array $changes = null,
        ?string $ipAddress = null
    ): self {
        $adminId = $admin instanceof User ? $admin->id : $admin;

        return self::create([
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->getKey(),
            'description' => $description,
            'changes' => $changes,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}

