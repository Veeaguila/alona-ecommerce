<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user105 = \App\Models\User::where('email', 'staff.laguna@alona.com')->first();
$passwordHash = $user105 ? $user105->password : bcrypt('password');

$userManila = \App\Models\User::firstOrCreate(
    ['email' => 'staff.manila@alona.com'],
    [
        'name' => 'Staff Manila',
        'password' => $passwordHash,
        'usertype' => 'sorting_center',
        'status' => 'approved',
    ]
);

$userManila->update([
    'usertype' => 'sorting_center',
    'status' => 'approved',
    'password' => $passwordHash,
]);

$staff = \App\Models\LogisticsHubStaff::updateOrCreate(
    ['user_id' => $userManila->id],
    [
        'sorting_center_id' => 1,
        'logistics_hub_id' => 1, // Central Manila Hub
        'is_active' => true,
    ]
);

echo "Setup staff.manila@alona.com:\n";
echo "  User ID: {$userManila->id}\n";
echo "  Name: {$userManila->name}\n";
echo "  Email: {$userManila->email}\n";
echo "  Usertype: {$userManila->usertype}\n";
echo "  Assigned Hub: Central Manila Hub (Hub ID: {$staff->logistics_hub_id})\n";

$dest = app(\App\Services\AccountDestination::class)->route($userManila->fresh());
echo "  Login Redirect Route: {$dest} -> " . route($dest) . "\n";
