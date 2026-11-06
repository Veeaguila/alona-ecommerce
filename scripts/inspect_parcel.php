<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\User;

$parcel = Parcel::with(['pickupRider.user', 'deliveryRider.user'])->find(3);
echo "=== PARCEL 3 ===\n";
echo json_encode($parcel, JSON_PRETTY_PRINT) . "\n";

$item = OrderItem::find(6);
echo "=== ORDER ITEM 6 ===\n";
echo json_encode($item, JSON_PRETTY_PRINT) . "\n";

$order = Order::with('user')->find(6);
echo "=== ORDER 6 ===\n";
echo "Order Number: {$order->order_number}\n";
echo "Buyer ID: {$order->user_id}\n";
echo "Buyer Name: {$order->user->name}\n";
echo "Buyer Email: {$order->user->email}\n";

