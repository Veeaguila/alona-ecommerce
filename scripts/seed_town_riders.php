<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Area, LogisticsHub, Rider, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$lagunaTowns = [
    ['town' => 'Alaminos', 'first' => 'Juanito', 'last' => 'Santos'],
    ['town' => 'Bay', 'first' => 'Mateo', 'last' => 'Reyes'],
    ['town' => 'Calauan', 'first' => 'Carlo', 'last' => 'Cruz'],
    ['town' => 'Cavinti', 'first' => 'Gabriel', 'last' => 'Bautista'],
    ['town' => 'City of Biñan', 'first' => 'Danilo', 'last' => 'Ramos'],
    ['town' => 'City of Cabuyao', 'first' => 'Mark', 'last' => 'Garcia'],
    ['town' => 'City of Calamba', 'first' => 'Antonio', 'last' => 'Mendoza'],
    ['town' => 'City of San Pablo', 'first' => 'Rafael', 'last' => 'Villanueva'],
    ['town' => 'City of San Pedro', 'first' => 'Christian', 'last' => 'Fernandez'],
    ['town' => 'City of Santa Rosa', 'first' => 'Paolo', 'last' => 'Lopez'],
    ['town' => 'Famy', 'first' => 'Eduardo', 'last' => 'Gonzales'],
    ['town' => 'Kalayaan', 'first' => 'Michael', 'last' => 'Aquino'],
    ['town' => 'Liliw', 'first' => 'Jerome', 'last' => 'Dela Cruz'],
    ['town' => 'Los Baños', 'first' => 'Richard', 'last' => 'Mercado'],
    ['town' => 'Luisiana', 'first' => 'Angelo', 'last' => 'Ocampo'],
    ['town' => 'Lumban', 'first' => 'Francis', 'last' => 'Castro'],
    ['town' => 'Mabitac', 'first' => 'Raymond', 'last' => 'Salazar'],
    ['town' => 'Magdalena', 'first' => 'Kenneth', 'last' => 'Navarro'],
    ['town' => 'Majayjay', 'first' => 'Dominic', 'last' => 'Pascual'],
    ['town' => 'Nagcarlan', 'first' => 'Vincent', 'last' => 'Soriano'],
    ['town' => 'Paete', 'first' => 'Gerald', 'last' => 'Tolentino'],
    ['town' => 'Pagsanjan', 'first' => 'Roderick', 'last' => 'Cortez'],
    ['town' => 'Pakil', 'first' => 'Marvin', 'last' => 'Manalo'],
    ['town' => 'Pangil', 'first' => 'Joel', 'last' => 'De Leon'],
    ['town' => 'Pila', 'first' => 'Bryan', 'last' => 'Espiritu'],
    ['town' => 'Rizal', 'first' => 'Adrian', 'last' => 'Del Rosario'],
    ['town' => 'Santa Cruz', 'first' => 'Jeffrey', 'last' => 'Valencia'],
    ['town' => 'Santa Maria', 'first' => 'Joseph', 'last' => 'Agoncillo'],
    ['town' => 'Siniloan', 'first' => 'Arnel', 'last' => 'Corpuz'],
    ['town' => 'Victoria', 'first' => 'Patrick', 'last' => 'Dizon'],
];

$manilaTowns = [
    ['town' => 'City of Caloocan', 'first' => 'Jonathan', 'last' => 'Tan'],
    ['town' => 'City of Las Piñas', 'first' => 'Ronald', 'last' => 'Morales'],
    ['town' => 'City of Makati', 'first' => 'Dennis', 'last' => 'David'],
    ['town' => 'City of Malabon', 'first' => 'Roderic', 'last' => 'Domingo'],
    ['town' => 'City of Mandaluyong', 'first' => 'Christopher', 'last' => 'Roxas'],
    ['town' => 'City of Manila', 'first' => 'Allan', 'last' => 'Miranda'],
    ['town' => 'City of Muntinlupa', 'first' => 'Ian', 'last' => 'De Guzman'],
    ['town' => 'City of Navotas', 'first' => 'Melvin', 'last' => 'Salvador'],
    ['town' => 'City of Parañaque', 'first' => 'Eric', 'last' => 'Javier'],
    ['town' => 'City of Pasig', 'first' => 'Ferdinand', 'last' => 'Yu'],
    ['town' => 'City of Quezon City', 'first' => 'Joshua', 'last' => 'Perez'],
    ['town' => 'City of San Juan', 'first' => 'Dexter', 'last' => 'Serrano'],
    ['town' => 'City of Taguig', 'first' => 'Leandro', 'last' => 'Magno'],
    ['town' => 'City of Valenzuela', 'first' => 'Oliver', 'last' => 'Pineda'],
    ['town' => 'Pasay City', 'first' => 'Marco', 'last' => 'Dimaculangan'],
    ['town' => 'Pateros', 'first' => 'Rommel', 'last' => 'Macaraeg'],
    ['town' => 'Quezon City', 'first' => 'Norman', 'last' => 'Lim'],
];

$lagunaHub = LogisticsHub::find(1);
$manilaHub = LogisticsHub::find(2);

$createdCount = 0;
$riderIndex = 3;

echo "Starting Rider Seeding...\n";

// 1. Seed Laguna Riders (Index 3 to 32)
foreach ($lagunaTowns as $entry) {
    $email = "rider{$riderIndex}@example.com";
    $mobile = sprintf("0917%07d", 100000 + $riderIndex);
    $plate = sprintf("LAG-%04d", 1000 + $riderIndex);
    $license = sprintf("D01-20-%06d", 100000 + $riderIndex);
    $fullName = "{$entry['first']} {$entry['last']}";

    $area = Area::where('sorting_center_id', 1)->where('name', $entry['town'])->first();
    if (!$area) {
        echo "WARNING: Area not found for Laguna town: {$entry['town']}\n";
        continue;
    }

    DB::transaction(function () use (
        $entry, $email, $mobile, $plate, $license, $fullName, $area, $lagunaHub, &$createdCount
    ) {
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $fullName,
                'first_name' => $entry['first'],
                'last_name' => $entry['last'],
                'email' => $email,
                'contact_no' => $mobile,
                'birthday' => '1995-05-15',
                'usertype' => 'rider',
                'status' => 'approved',
                'email_verified_at' => now(),
                'password' => Hash::make('rider@123'),
            ]);
        }

        $rider = Rider::where('user_id', $user->id)->first();
        if (!$rider) {
            $rider = Rider::create([
                'user_id' => $user->id,
                'sorting_center_id' => 1,
                'logistics_hub_id' => $lagunaHub ? $lagunaHub->id : 1,
                'name' => $fullName,
                'contact' => $mobile,
                'birthday' => '1995-05-15',
                'vehicle_type' => 'Motorcycle',
                'plate_number' => $plate,
                'license_number' => $license,
                'license_expiry' => '2028-12-31',
                'license_doc_url' => 'rider-documents/bAnllQJbas3rRQXYHJQ12NV86f9FxuqbW7WizxN7.png',
                'or_cr_doc_url' => 'rider-documents/GOjHOQtKp8y9jxiac4323Y8KiF5eORAJw1MO8Lyw.png',
                'approval_status' => 'approved',
                'is_active' => true,
            ]);
        } else {
            $rider->update([
                'logistics_hub_id' => $lagunaHub ? $lagunaHub->id : 1,
                'vehicle_type' => 'Motorcycle',
                'approval_status' => 'approved',
                'is_active' => true,
            ]);
        }

        $rider->areas()->sync([$area->id]);
        $createdCount++;
        echo "Laguna: {$email} -> {$fullName} ({$entry['town']}) [Hub: {$lagunaHub->name}]\n";
    });

    $riderIndex++;
}

// 2. Seed Manila Riders (Index 33 to 49)
foreach ($manilaTowns as $entry) {
    $email = "rider{$riderIndex}@example.com";
    $mobile = sprintf("0918%07d", 200000 + $riderIndex);
    $plate = sprintf("MNL-%04d", 2000 + $riderIndex);
    $license = sprintf("N01-20-%06d", 200000 + $riderIndex);
    $fullName = "{$entry['first']} {$entry['last']}";

    $area = Area::where('sorting_center_id', 1)->where('name', $entry['town'])->first();
    if (!$area) {
        echo "WARNING: Area not found for Manila town: {$entry['town']}\n";
        continue;
    }

    DB::transaction(function () use (
        $entry, $email, $mobile, $plate, $license, $fullName, $area, $manilaHub, &$createdCount
    ) {
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $fullName,
                'first_name' => $entry['first'],
                'last_name' => $entry['last'],
                'email' => $email,
                'contact_no' => $mobile,
                'birthday' => '1993-08-20',
                'usertype' => 'rider',
                'status' => 'approved',
                'email_verified_at' => now(),
                'password' => Hash::make('rider@123'),
            ]);
        }

        $rider = Rider::where('user_id', $user->id)->first();
        if (!$rider) {
            $rider = Rider::create([
                'user_id' => $user->id,
                'sorting_center_id' => 1,
                'logistics_hub_id' => $manilaHub ? $manilaHub->id : 2,
                'name' => $fullName,
                'contact' => $mobile,
                'birthday' => '1993-08-20',
                'vehicle_type' => 'Motorcycle',
                'plate_number' => $plate,
                'license_number' => $license,
                'license_expiry' => '2028-12-31',
                'license_doc_url' => 'rider-documents/bAnllQJbas3rRQXYHJQ12NV86f9FxuqbW7WizxN7.png',
                'or_cr_doc_url' => 'rider-documents/GOjHOQtKp8y9jxiac4323Y8KiF5eORAJw1MO8Lyw.png',
                'approval_status' => 'approved',
                'is_active' => true,
            ]);
        } else {
            $rider->update([
                'logistics_hub_id' => $manilaHub ? $manilaHub->id : 2,
                'vehicle_type' => 'Motorcycle',
                'approval_status' => 'approved',
                'is_active' => true,
            ]);
        }

        $rider->areas()->sync([$area->id]);
        $createdCount++;
        echo "Manila: {$email} -> {$fullName} ({$entry['town']}) [Hub: {$manilaHub->name}]\n";
    });

    $riderIndex++;
}

echo "\nCOMPLETED! Total town riders set up: {$createdCount}\n";

