<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Models\Rider;
use App\Models\Order;

$parcel = Parcel::find(3);
echo "Parcel 3:\n";
echo "Status: {$parcel->status}\n";
echo "Pickup Rider ID: {$parcel->pickup_rider_id}\n";
echo "Assigned Rider ID: {$parcel->assigned_rider_id}\n";

if ($parcel->assigned_rider_id) {
    $rider = Rider::with('user')->find($parcel->assigned_rider_id);
    echo "Assigned Rider: " . ($rider->user->name ?? 'N/A') . " (" . ($rider->user->email ?? 'N/A') . ")\n";
} else {
    echo "Assigned Rider is NULL\n";
}

$order = Order::find(6);
echo "\nOrder 6:\n";
echo "Order Number: {$order->order_number}\n";
echo "Status: {$order->status}\n";

