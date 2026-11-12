<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{LogisticsHub, LogisticsHubStaff, SortingCenter, User};
use Illuminate\Support\Facades\Hash;

$center = SortingCenter::firstOrFail();
$manilaHub = LogisticsHub::where('sorting_center_id', $center->id)->where('name', 'like', '%Manila%')->firstOrFail();
$lagunaHub = LogisticsHub::where('sorting_center_id', $center->id)->where('name', 'like', '%Laguna%')->firstOrFail();

$accounts = [
    [
        'email' => 'staff.manila@alona.com',
        'name' => 'Manila Hub Staff',
        'hub' => $manilaHub,
    ],
    [
        'email' => 'staff.laguna@alona.com',
        'name' => 'Laguna Hub Staff',
        'hub' => $lagunaHub,
    ],
];

foreach ($accounts as $acc) {
    $user = User::firstOrNew(['email' => $acc['email']]);
    $user->name = $acc['name'];
    $user->password = Hash::make('Password123!');
    $user->usertype = 'sorting_center';
    $user->status = 'approved';
    $user->email_verified_at = now();
    $user->save();

    $staff = LogisticsHubStaff::firstOrNew(['user_id' => $user->id]);
    $staff->sorting_center_id = $center->id;
    $staff->logistics_hub_id = $acc['hub']->id;
    $staff->is_active = true;
    $staff->save();

    echo "Created/Updated Hub Staff: {$acc['name']} ({$acc['email']}) -> {$acc['hub']->name}\n";
}
