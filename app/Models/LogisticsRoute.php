<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsRoute extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function originHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'origin_hub_id'); }
    public function destinationHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'destination_hub_id'); }
}
