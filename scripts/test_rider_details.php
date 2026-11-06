<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Services\RiderParcelDetails;
use Illuminate\Database\Eloquent\Collection;

$parcel = Parcel::find(3);
$service = app(RiderParcelDetails::class);
$collection = new Collection([$parcel]);
$service->append($collection, false);

echo json_encode([
    'id' => $parcel->id,
    'order_id' => $parcel->order_id,
    'status' => $parcel->status,
    'is_cod' => $parcel->is_cod,
    'cod_amount' => $parcel->cod_amount,
    'payment_method' => $parcel->payment_method,
    'order_number' => $parcel->order_number,
    'item_name' => $parcel->item_name,
    'item_quantity' => $parcel->item_quantity,
    'buyer_note' => $parcel->buyer_note,
    'address_details' => $parcel->address_details,
], JSON_PRETTY_PRINT);

