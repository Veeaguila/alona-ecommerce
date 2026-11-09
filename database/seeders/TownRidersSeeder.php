<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\RiderPresence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TownRidersSeeder extends Seeder
{
    public function run(): void
    {
        $center = SortingCenter::first();
        if (!$center) {
            $this->command?->error('No SortingCenter found!');
            return;
        }

        // 1. Ensure Laguna Hub and Manila Hub exist
        $lagunaHub = LogisticsHub::where('sorting_center_id', $center->id)
            ->where(function ($q) {
                $q->where('code', 'LAG-01')->orWhere('name', 'like', '%Laguna%');
            })->first();

        if (!$lagunaHub) {
            $lagunaHub = LogisticsHub::create([
                'sorting_center_id' => $center->id,
                'code' => 'LAG-01',
                'name' => 'Laguna Operation Hub',
                'address' => 'National Highway, San Pedro, Laguna',
                'city_code' => '0403425000',
                'pickup_city_codes' => [],
                'delivery_city_codes' => [],
                'is_active' => true,
            ]);
        }

        $manilaHub = LogisticsHub::where('sorting_center_id', $center->id)
            ->where(function ($q) {
                $q->where('code', 'MNL-01')->orWhere('name', 'like', '%Manila%');
            })->first();

        if (!$manilaHub) {
            $manilaHub = LogisticsHub::create([
                'sorting_center_id' => $center->id,
                'code' => 'MNL-01',
                'name' => 'Central Manila Hub',
                'address' => '100 EDSA, Balintawak, Quezon City, Metro Manila',
                'city_code' => '1381300000',
                'pickup_city_codes' => [],
                'delivery_city_codes' => [],
                'is_active' => true,
            ]);
        }

        // 2. Base Riders: rider.connectiontest, rider1, rider2
        $baseRiders = [
            [
                'email' => 'rider.connectiontest@example.com',
                'name' => 'Connection Test',
                'first_name' => 'Connection',
                'last_name' => 'Test',
                'contact' => '09170000000',
                'plate' => 'TEST-000',
                'license' => 'T01-20-000000',
                'hub_id' => $manilaHub->id,
                'areas' => ['Quezon City'],
            ],
            [
                'email' => 'rider1@example.com',
                'name' => 'Ethan Lustre',
                'first_name' => 'Ethan',
                'last_name' => 'Lustre',
                'contact' => '09171000001',
                'plate' => 'LAG-1001',
                'license' => 'D01-20-100001',
                'hub_id' => $lagunaHub->id,
                'areas' => ['City of San Pedro', 'City of Biñan', 'City of Santa Rosa'],
            ],
            [
                'email' => 'rider2@example.com',
                'name' => 'Noah Alcaraz',
                'first_name' => 'Noah',
                'last_name' => 'Alcaraz',
                'contact' => '09182000002',
                'plate' => 'MNL-2002',
                'license' => 'N01-20-200002',
                'hub_id' => $manilaHub->id,
                'areas' => ['Quezon City', 'City of Manila', 'City of Makati'],
            ],
        ];

        // 3. 30 Laguna Towns (rider3 to rider32)
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

        // 4. 17 Manila Towns (rider33 to rider49)
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

        User::withoutEvents(function () use ($baseRiders, $center, $lagunaHub, $manilaHub, $lagunaTowns, $manilaTowns) {
            foreach ($baseRiders as $b) {
                $user = User::firstOrCreate(
                    ['email' => $b['email']],
                    [
                        'name' => $b['name'],
                        'first_name' => $b['first_name'],
                        'last_name' => $b['last_name'],
                        'contact_no' => $b['contact'],
                        'birthday' => '1995-05-15',
                        'usertype' => 'rider',
                        'status' => 'approved',
                        'email_verified_at' => now(),
                        'password' => Hash::make('rider@123'),
                    ]
                );
                $user->update([
                    'name' => $b['name'],
                    'first_name' => $b['first_name'],
                    'last_name' => $b['last_name'],
                    'usertype' => 'rider',
                    'status' => 'approved',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                $rider = Rider::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'sorting_center_id' => $center->id,
                        'logistics_hub_id' => $b['hub_id'],
                        'name' => $b['name'],
                        'contact' => $b['contact'],
                        'birthday' => '1995-05-15',
                        'vehicle_type' => 'Motorcycle',
                        'plate_number' => $b['plate'],
                        'license_number' => $b['license'],
                        'license_expiry' => '2028-12-31',
                        'approval_status' => 'approved',
                        'is_active' => true,
                    ]
                );
                $rider->update([
                    'sorting_center_id' => $center->id,
                    'logistics_hub_id' => $b['hub_id'],
                    'name' => $b['name'],
                    'contact' => $b['contact'],
                    'vehicle_type' => 'Motorcycle',
                    'approval_status' => 'approved',
                    'is_active' => true,
                    'activation_token' => null,
                ]);

                $areaIds = Area::where('sorting_center_id', $center->id)->whereIn('name', $b['areas'])->pluck('id')->all();
                if ($areaIds) {
                    $rider->areas()->sync($areaIds);
                }
            }

            $riderIndex = 3;

            // Seed 30 Laguna Riders
            foreach ($lagunaTowns as $entry) {
                $email = "rider{$riderIndex}@example.com";
                $mobile = sprintf("0917%07d", 100000 + $riderIndex);
                $plate = sprintf("LAG-%04d", 1000 + $riderIndex);
                $license = sprintf("D01-20-%06d", 100000 + $riderIndex);
                $fullName = "{$entry['first']} {$entry['last']}";

                $area = Area::where('sorting_center_id', $center->id)
                    ->where(function ($q) use ($entry) {
                        $q->where('name', $entry['town'])->orWhere('name', 'like', "%{$entry['town']}%");
                        if ($entry['town'] === 'City of Quezon City') {
                            $q->orWhere('name', 'Quezon City');
                        }
                    })->first();

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $fullName,
                        'first_name' => $entry['first'],
                        'last_name' => $entry['last'],
                        'contact_no' => $mobile,
                        'birthday' => '1995-05-15',
                        'usertype' => 'rider',
                        'status' => 'approved',
                        'email_verified_at' => now(),
                        'password' => Hash::make('rider@123'),
                    ]
                );

                $user->update([
                    'name' => $fullName,
                    'first_name' => $entry['first'],
                    'last_name' => $entry['last'],
                    'usertype' => 'rider',
                    'status' => 'approved',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                $rider = Rider::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'sorting_center_id' => $center->id,
                        'logistics_hub_id' => $lagunaHub->id,
                        'name' => $fullName,
                        'contact' => $mobile,
                        'birthday' => '1995-05-15',
                        'vehicle_type' => 'Motorcycle',
                        'plate_number' => $plate,
                        'license_number' => $license,
                        'license_expiry' => '2028-12-31',
                        'approval_status' => 'approved',
                        'is_active' => true,
                    ]
                );

                $rider->update([
                    'sorting_center_id' => $center->id,
                    'logistics_hub_id' => $lagunaHub->id,
                    'name' => $fullName,
                    'contact' => $mobile,
                    'vehicle_type' => 'Motorcycle',
                    'approval_status' => 'approved',
                    'is_active' => true,
                    'activation_token' => null,
                ]);

                if ($area) {
                    $rider->areas()->sync([$area->id]);
                }

                $riderIndex++;
            }

            // Seed 17 Manila Riders
            foreach ($manilaTowns as $entry) {
                $email = "rider{$riderIndex}@example.com";
                $mobile = sprintf("0918%07d", 200000 + $riderIndex);
                $plate = sprintf("MNL-%04d", 2000 + $riderIndex);
                $license = sprintf("N01-20-%06d", 200000 + $riderIndex);
                $fullName = "{$entry['first']} {$entry['last']}";

                $area = Area::where('sorting_center_id', $center->id)
                    ->where(function ($q) use ($entry) {
                        $q->where('name', $entry['town'])->orWhere('name', 'like', "%{$entry['town']}%");
                        if ($entry['town'] === 'City of Quezon City') {
                            $q->orWhere('name', 'Quezon City');
                        }
                    })->first();

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $fullName,
                        'first_name' => $entry['first'],
                        'last_name' => $entry['last'],
                        'contact_no' => $mobile,
                        'birthday' => '1993-08-20',
                        'usertype' => 'rider',
                        'status' => 'approved',
                        'email_verified_at' => now(),
                        'password' => Hash::make('rider@123'),
                    ]
                );

                $user->update([
                    'name' => $fullName,
                    'first_name' => $entry['first'],
                    'last_name' => $entry['last'],
                    'usertype' => 'rider',
                    'status' => 'approved',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                $rider = Rider::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'sorting_center_id' => $center->id,
                        'logistics_hub_id' => $manilaHub->id,
                        'name' => $fullName,
                        'contact' => $mobile,
                        'birthday' => '1993-08-20',
                        'vehicle_type' => 'Motorcycle',
                        'plate_number' => $plate,
                        'license_number' => $license,
                        'license_expiry' => '2028-12-31',
                        'approval_status' => 'approved',
                        'is_active' => true,
                    ]
                );

                $rider->update([
                    'sorting_center_id' => $center->id,
                    'logistics_hub_id' => $manilaHub->id,
                    'name' => $fullName,
                    'contact' => $mobile,
                    'vehicle_type' => 'Motorcycle',
                    'approval_status' => 'approved',
                    'is_active' => true,
                    'activation_token' => null,
                ]);

                if ($area) {
                    $rider->areas()->sync([$area->id]);
                }

                $riderIndex++;
            }
        });

        $this->command?->info("TownRidersSeeder completed! Restored all riders (rider1 to rider49 + connectiontest).");
    }
}
