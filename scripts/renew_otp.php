<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Services\DeliveryOtp;

$parcel = Parcel::find(3);
$service = app(DeliveryOtp::class);
$service->renew($parcel);
$data = $service->forBuyer($parcel);

echo "FRESH OTP: {$data['otp']}\n";
echo "Expires at: {$data['expires_at']}\n";

