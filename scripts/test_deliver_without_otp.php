<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Models\User;
use App\Http\Controllers\Logistics\RiderDeliveryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Parcel 3 assigned to Adrian Del Rosario (rider28@example.com)
$user = User::where('email', 'rider28@example.com')->first();
$controller = app(RiderDeliveryController::class);

$request = Request::create('/api/rider/deliveries/3/deliver', 'POST');
$request->setUserResolver(fn () => $user);

try {
    $response = $controller->deliver($request, 3);
    echo "STATUS: " . $response->getStatusCode() . "\n";
    echo "DATA: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

