<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsHubStaff extends Model
{
    protected $table = 'logistics_hub_staff';
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(SortingCenter::class); }
    public function hub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id'); }
}
