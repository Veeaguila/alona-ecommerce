<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$recentUsers = \App\Models\User::orderBy('id', 'desc')->take(10)->get(['id', 'name', 'email', 'usertype', 'created_at']);
foreach ($recentUsers as $u) {
    echo "ID: {$u->id} | {$u->name} | {$u->email} | {$u->usertype} | {$u->created_at}\n";
}
