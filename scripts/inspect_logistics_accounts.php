<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$centers = \App\Models\SortingCenter::with('user:id,name,email')->get(['id','business_name','user_id']);
$hubs = \App\Models\LogisticsHub::get(['id','name','code','sorting_center_id']);
$staff = \App\Models\LogisticsHubStaff::with('user:id,name,email','hub:id,name')->get();
$riders = \App\Models\Rider::with('user:id,name,email','hub:id,name')->get(['id','user_id','logistics_hub_id','name','vehicle_type','approval_status','is_active']);

echo "=== SORTING CENTERS (COMPANY ADMINS) ===\n";
foreach ($centers as $c) {
    echo "ID: {$c->id} | {$c->business_name} | User: {$c->user?->name} ({$c->user?->email})\n";
}

echo "\n=== HUBS ===\n";
foreach ($hubs as $h) {
    echo "ID: {$h->id} | {$h->name} ({$h->code}) | Center: {$h->sorting_center_id}\n";
}

echo "\n=== HUB STAFF ===\n";
foreach ($staff as $s) {
    echo "ID: {$s->id} | User: {$s->user?->name} ({$s->user?->email}) | Hub: {$s->hub?->name} | Active: " . ($s->is_active ? 'YES' : 'NO') . "\n";
}

echo "\n=== RIDERS ===\n";
foreach ($riders as $r) {
    echo "ID: {$r->id} | Rider: {$r->name} ({$r->user?->email}) | Hub: {$r->hub?->name} | Status: {$r->approval_status}\n";
}
