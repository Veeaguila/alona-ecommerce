<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DeliveryProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'parcel_id',
        'rider_id',
        'type',
        'file_path',
        'notes',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function privateFilePath(): ?string
    {
        $path = $this->file_path;
        if (!is_string($path) || !str_starts_with($path, 'delivery-proofs/') || str_contains($path, '..')) return null;
        try {
            $full = Storage::disk('local')->path($path);
            return is_file($full) && is_readable($full) ? $full : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Current completion eligibility only; never rewrites historical delivery/proof records. */
    public function isValidPhoto(): bool
    {
        $path = $this->privateFilePath();
        if ($this->type !== 'PHOTO' || !$path) return false;
        try {
            $size = filesize($path);
            if (!$size || $size > 20 * 1024 * 1024) return false;
            $image = @getimagesize($path);
            return $image && $image[0] > 0 && $image[1] > 0 && $image[0] <= 6000 && $image[1] <= 6000
                && in_array($image['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/x-ms-bmp', 'image/webp'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
