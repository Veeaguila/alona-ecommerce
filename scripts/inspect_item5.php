<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PickupRequest;
use App\Models\Parcel;

$prs = PickupRequest::all();
echo "Pickup Requests count: " . $prs->count() . "\n";
foreach ($prs as $pr) {
    echo json_encode($pr->toArray(), JSON_PRETTY_PRINT) . "\n";
}

$parcels = Parcel::all();
echo "\nParcels count: " . $parcels->count() . "\n";
foreach ($parcels as $p) {
    echo json_encode($p->toArray(), JSON_PRETTY_PRINT) . "\n";
}
