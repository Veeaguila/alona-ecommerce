<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrderItem;
$item = OrderItem::with(['order', 'product'])->find(6);
echo json_encode([
    'order_number' => $item->order->order_number,
    'payment_method' => $item->order->payment_method,
    'payment_status' => $item->order->payment_status,
    'total' => $item->order->total,
    'buyer_note' => $item->order->buyer_note,
    'product' => $item->product->name ?? '',
    'quantity' => $item->quantity ?? 1,
], JSON_PRETTY_PRINT);

