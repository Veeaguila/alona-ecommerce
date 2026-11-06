<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PickupRequest;
foreach (PickupRequest::with('parcel')->get() as $pr) {
    echo "ID: {$pr->id} | Parcel: {$pr->parcel_id} | Tracking: " . ($pr->parcel->order_id ?? 'N/A') . " | Status: {$pr->status} | ParcelStatus: " . ($pr->parcel->status ?? 'N/A') . "\n";
}

