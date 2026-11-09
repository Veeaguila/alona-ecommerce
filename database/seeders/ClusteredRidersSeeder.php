<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\LogisticsHub;
use App\Models\LogisticsRoute;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\DeliveryCoverageService;
use App\Services\PhilippineAddressService;
use App\Services\RiderPresence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClusteredRidersSeeder extends Seeder
{
    public function run(): void
    {
        $center = SortingCenter::first();
        if (!$center) {
            $this->command?->error('No SortingCenter found! Run LogisticsCompleteSeeder first.');
            return;
        }

        $addressService = app(PhilippineAddressService::class);
        $coverageService = app(DeliveryCoverageService::class);
        $geo = $addressService->data();

        // 1. Gather all 30 Laguna city codes
        $lagunaCodes = [];
        foreach ($geo['cities'] as $c) {
            if (($c['province_code'] ?? '') === '0403400000') {
                $lagunaCodes[] = $c['code'];
            }
        }

        // 2. Gather all 17 NCR city codes
        $ncrCodes = [];
        foreach ($geo['cities'] as $c) {
            if (($c['region_code'] ?? '') === '1300000000') {
                $ncrCodes[] = $c['code'];
            }
        }

        // 3. Ensure Laguna Hub exists
        $lagunaHub = LogisticsHub::firstOrCreate(
            ['sorting_center_id' => $center->id, 'code' => 'LAG-01'],
            [
                'name' => 'Laguna Operations Hub',
                'address' => 'National Highway, San Pedro, Laguna',
                'city_code' => '0403425000',
                'pickup_city_codes' => $lagunaCodes,
                'delivery_city_codes' => $lagunaCodes,
                'is_active' => true,
            ]
        );
        // Update codes to ensure full coverage
        $lagunaHub->update([
            'pickup_city_codes' => $lagunaCodes,
            'delivery_city_codes' => $lagunaCodes,
            'is_active' => true,
        ]);

        // 4. Update Manila Hub to cover all 17 NCR cities
        $manilaHub = LogisticsHub::where('sorting_center_id', $center->id)->where('code', 'MNL-01')->first();
        if ($manilaHub) {
            $manilaHub->update([
                'pickup_city_codes' => $ncrCodes,
                'delivery_city_codes' => $ncrCodes,
                'is_active' => true,
            ]);
        }

        // 5. Inter-hub routes between Manila and Laguna
        if ($manilaHub && $lagunaHub) {
            LogisticsRoute::firstOrCreate(
                ['sorting_center_id' => $center->id, 'origin_hub_id' => $manilaHub->id, 'destination_hub_id' => $lagunaHub->id],
                ['transport_mode' => 'truck', 'is_active' => true]
            );
            LogisticsRoute::firstOrCreate(
                ['sorting_center_id' => $center->id, 'origin_hub_id' => $lagunaHub->id, 'destination_hub_id' => $manilaHub->id],
                ['transport_mode' => 'truck', 'is_active' => true]
            );
        }

        // 6. Ensure all Laguna and NCR cities exist in areas table for this sorting center
        $coverageService->addCities($center, array_merge($lagunaCodes, $ncrCodes));

        // 7. Define the 9 Clusters
        $clusters = [
            // Laguna Clusters
            [
                'email' => 'rider.l1@shopalona.online',
                'name' => 'Rider L1 - West Laguna',
                'contact' => '09171000001',
                'plate' => 'MC-LAG-01',
                'hub_id' => $lagunaHub->id,
                'city_codes' => [
                    '0403425000', // City of San Pedro
                    '0403403000', // City of Biñan
                    '0403428000', // City of Santa Rosa
                ],
            ],
            [
                'email' => 'rider.l2@shopalona.online',
                'name' => 'Rider L2 - Central Laguna',
                'contact' => '09171000002',
                'plate' => 'MC-LAG-02',
                'hub_id' => $lagunaHub->id,
                'city_codes' => [
                    '0403404000', // City of Cabuyao
                    '0403405000', // City of Calamba
                    '0403411000', // Los Baños
                    '0403402000', // Bay
                ],
            ],
            [
                'email' => 'rider.l3@shopalona.online',
                'name' => 'Rider L3 - South Laguna',
                'contact' => '09171000003',
                'plate' => 'MC-LAG-03',
                'hub_id' => $lagunaHub->id,
                'city_codes' => [
                    '0403424000', // City of San Pablo
                    '0403401000', // Alaminos
                    '0403406000', // Calauan
                    '0403430000', // Victoria
                    '0403422000', // Pila
                ],
            ],
            [
                'email' => 'rider.l4@shopalona.online',
                'name' => 'Rider L4 - East Laguna',
                'contact' => '09171000004',
                'plate' => 'MC-LAG-04',
                'hub_id' => $lagunaHub->id,
                'city_codes' => [
                    '0403426000', // Santa Cruz
                    '0403419000', // Pagsanjan
                    '0403415000', // Magdalena
                    '0403416000', // Majayjay
                    '0403410000', // Liliw
                    '0403417000', // Nagcarlan
                    '0403423000', // Rizal
                ],
            ],
            [
                'email' => 'rider.l5@shopalona.online',
                'name' => 'Rider L5 - Far East Laguna',
                'contact' => '09171000005',
                'plate' => 'MC-LAG-05',
                'hub_id' => $lagunaHub->id,
                'city_codes' => [
                    '0403407000', // Cavinti
                    '0403412000', // Luisiana
                    '0403413000', // Lumban
                    '0403409000', // Kalayaan
                    '0403418000', // Paete
                    '0403420000', // Pakil
                    '0403421000', // Pangil
                    '0403429000', // Siniloan
                    '0403408000', // Famy
                    '0403414000', // Mabitac
                    '0403427000', // Santa Maria
                ],
            ],
            // Manila Clusters
            [
                'email' => 'rider.m1@shopalona.online',
                'name' => 'Rider M1 - CAMANAVA',
                'contact' => '09172000001',
                'plate' => 'MC-NCR-01',
                'hub_id' => $manilaHub?->id ?? 1,
                'city_codes' => [
                    '1380100000', // City of Caloocan
                    '1380400000', // City of Malabon
                    '1380900000', // City of Navotas
                    '1381600000', // City of Valenzuela
                ],
            ],
            [
                'email' => 'rider.m2@shopalona.online',
                'name' => 'Rider M2 - Central NCR',
                'contact' => '09172000002',
                'plate' => 'MC-NCR-02',
                'hub_id' => $manilaHub?->id ?? 1,
                'city_codes' => [
                    '1380600000', // City of Manila
                    '1381300000', // Quezon City
                    '1381400000', // City of San Juan
                    '1380500000', // City of Mandaluyong
                ],
            ],
            [
                'email' => 'rider.m3@shopalona.online',
                'name' => 'Rider M3 - East NCR',
                'contact' => '09172000003',
                'plate' => 'MC-NCR-03',
                'hub_id' => $manilaHub?->id ?? 1,
                'city_codes' => [
                    '1380300000', // City of Makati
                    '1381200000', // City of Pasig
                    '1381500000', // City of Taguig
                    '1381701000', // Pateros
                    '1380700000', // City of Marikina
                ],
            ],
            [
                'email' => 'rider.m4@shopalona.online',
                'name' => 'Rider M4 - South NCR',
                'contact' => '09172000004',
                'plate' => 'MC-NCR-04',
                'hub_id' => $manilaHub?->id ?? 1,
                'city_codes' => [
                    '1381100000', // Pasay City
                    '1381000000', // City of Parañaque
                    '1380200000', // City of Las Piñas
                    '1380800000', // City of Muntinlupa
                ],
            ],
        ];

        $presenceService = app(RiderPresence::class);

        foreach ($clusters as $cData) {
            $user = User::firstOrCreate(
                ['email' => $cData['email']],
                [
                    'name' => $cData['name'],
                    'password' => Hash::make('password'),
                    'usertype' => 'rider',
                    'status' => 'approved',
                    'contact_no' => $cData['contact'],
                    'email_verified_at' => now(),
                ]
            );

            // Ensure user fields are approved
            $user->update([
                'name' => $cData['name'],
                'usertype' => 'rider',
                'status' => 'approved',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            $rider = Rider::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'sorting_center_id' => $center->id,
                    'logistics_hub_id' => $cData['hub_id'],
                    'name' => $cData['name'],
                    'contact' => $cData['contact'],
                    'vehicle_type' => 'motorcycle',
                    'plate_number' => $cData['plate'],
                    'approval_status' => 'approved',
                    'is_active' => true,
                ]
            );

            $rider->update([
                'sorting_center_id' => $center->id,
                'logistics_hub_id' => $cData['hub_id'],
                'name' => $cData['name'],
                'contact' => $cData['contact'],
                'vehicle_type' => 'motorcycle',
                'plate_number' => $cData['plate'],
                'approval_status' => 'approved',
                'is_active' => true,
                'activation_token' => null,
                'activation_token_expires_at' => null,
            ]);

            // Sync assigned cluster area IDs
            $areaIds = Area::where('sorting_center_id', $center->id)
                ->whereIn('city_code', $cData['city_codes'])
                ->pluck('id')
                ->all();

            $rider->areas()->sync($areaIds);

            // Set presence online
            try {
                $presenceService->command($rider, 'go_online', 'seeder');
            } catch (\Throwable $e) {
                // Ignore presence cache lock issues
            }

            $this->command?->info("Rider [{$cData['name']}] ({$cData['email']}) synced with " . count($areaIds) . " delivery areas.");
        }
    }
}
