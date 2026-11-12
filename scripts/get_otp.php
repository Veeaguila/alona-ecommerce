<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Services\DeliveryOtp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

$parcel = Parcel::find(3);
if (!$parcel) {
    echo "Parcel 3 not found!\n";
    exit;
}

echo "Parcel 3 Status: {$parcel->status}\n";

$key = DeliveryOtp::CACHE_PREFIX . $parcel->id;
$cached = Cache::get($key);

if ($cached) {
    echo "Cache entry found!\n";
    echo "Expires at: " . date('Y-m-d H:i:s', $cached['expires_at']) . " (Now: " . date('Y-m-d H:i:s') . ")\n";
    echo "Attempts: " . $cached['attempts'] . "\n";
    echo "Consumed: " . ($cached['consumed'] ? 'yes' : 'no') . "\n";
    if (!empty($cached['ciphertext'])) {
        try {
            $otp = Crypt::decryptString($cached['ciphertext']);
            echo "DECRYPTED OTP: {$otp}\n";
        } catch (\Exception $e) {
            echo "Could not decrypt: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "No OTP in cache for key: {$key}. Generating one via DeliveryOtp service...\n";
    $service = app(DeliveryOtp::class);
    $buyerData = $service->forBuyer($parcel);
    echo "Generated OTP: " . $buyerData['otp'] . " (Expires at: " . $buyerData['expires_at'] . ")\n";
}

