<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$users = \App\Models\User::where('usertype', 'sorting_center')
    ->orWhere('usertype', 'hub_staff')
    ->orWhere('email', 'like', '%alona.com')
    ->get(['id', 'name', 'email', 'usertype', 'status']);

foreach ($users as $u) {
    echo "ID: {$u->id} | {$u->name} | {$u->email} | {$u->usertype} | {$u->status}\n";
}
