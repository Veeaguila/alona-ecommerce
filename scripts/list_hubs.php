<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (\App\Models\LogisticsHub::all() as $h) {
    echo "Hub ID: {$h->id} | Code: {$h->code} | Name: {$h->name} | Center ID: {$h->sorting_center_id}\n";
}
