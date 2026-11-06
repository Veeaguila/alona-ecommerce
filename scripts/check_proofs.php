<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Parcel;
use App\Models\DeliveryProof;

$parcel = Parcel::find(3);
echo "Parcel 3 status: {$parcel->status}\n";
$proofs = DeliveryProof::where('parcel_id', 3)->get();
echo "Proofs count: {$proofs->count()}\n";
foreach ($proofs as $p) {
    echo "Proof ID: {$p->id}, type: {$p->type}, path: {$p->file_path}\n";
}

