<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PickupRequest;
use App\Models\Parcel;
use App\Models\OrderItem;

$item5 = OrderItem::find(5);
echo "Item 5 status: " . ($item5->status ?? 'N/A') . "\n";

$pr2 = PickupRequest::find(2);
if ($pr2) {
    echo "Updating PickupRequest 2 to cancelled (was: {$pr2->status})\n";
    $pr2->update(['status' => 'cancelled']);
}

$p2 = Parcel::find(2);
if ($p2) {
    echo "Updating Parcel 2 to CANCELLED (was: {$p2->status})\n";
    $p2->update(['status' => 'CANCELLED']);
}

echo "Cleanup complete!\n";

