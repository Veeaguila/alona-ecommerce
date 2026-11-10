<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('email', 'staff.laguna@alona.com')->first();
if (!$user) {
    echo "User not found.\n";
    exit(1);
}

// 1. Set usertype to sorting_center and status approved
$user->update([
    'usertype' => 'sorting_center',
    'status' => 'approved',
]);

// 2. Attach to Laguna Operations Hub (Hub ID: 3, Sorting Center ID: 1)
$staff = \App\Models\LogisticsHubStaff::updateOrCreate(
    ['user_id' => $user->id],
    [
        'sorting_center_id' => 1,
        'logistics_hub_id' => 3,
        'is_active' => true,
    ]
);

echo "Fixed user {$user->id} ({$user->email}):\n";
echo "  Usertype: {$user->usertype}\n";
echo "  Status: {$user->status}\n";
echo "  Assigned Hub: Laguna Operations Hub (Hub ID: {$staff->logistics_hub_id})\n";

// Verify destination route
$dest = app(\App\Services\AccountDestination::class)->route($user->fresh());
echo "  Login Redirect Route: {$dest} -> " . route($dest) . "\n";
