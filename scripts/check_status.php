<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Rider;
use App\Models\Parcel;
use App\Models\Order;
use App\Models\BuyerOtp;
use App\Models\DeliveryAttempt;
use App\Models\ParcelHubJourney;

echo "=== RIDERS ===\n";
$riders = Rider::with('user')->get();
foreach ($riders as $r) {
    echo "ID: {$r->id} | Name: " . ($r->user->name ?? 'N/A') . " | Email: " . ($r->user->email ?? 'N/A') . " | Hub: {$r->logistics_hub_id} | Status: {$r->status} | Active: {$r->is_active}\n";
}

echo "\n=== PARCELS ===\n";
$parcels = Parcel::all();
foreach ($parcels as $p) {
    echo "ID: {$p->id} | Tracking: {$p->tracking_number} | Status: {$p->status} | OrderItem: {$p->order_item_id} | PickupRider: {$p->pickup_rider_id} | DeliveryRider: {$p->delivery_rider_id}\n";
}

echo "\n=== HUB JOURNEYS ===\n";
$journeys = ParcelHubJourney::all();
foreach ($journeys as $j) {
    echo "ID: {$j->id} | ParcelID: {$j->parcel_id} | OriginHub: {$j->origin_hub_id} | CurrentHub: {$j->current_hub_id} | DestHub: {$j->destination_hub_id} | Status: {$j->status}\n";
}

echo "\n=== ORDERS ===\n";
$orders = Order::all();
foreach ($orders as $o) {
    echo "ID: {$o->id} | OrderNumber: {$o->order_number} | Status: {$o->status}\n";
}

echo "\n=== BUYER OTPS ===\n";
if (class_exists(BuyerOtp::class)) {
    $otps = BuyerOtp::all();
    foreach ($otps as $otp) {
        echo json_encode($otp) . "\n";
    }
} else {
    echo "BuyerOtp model not found\n";
}

