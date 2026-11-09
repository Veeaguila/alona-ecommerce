<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Area;
use App\Models\LogisticsHub;
use App\Models\LogisticsHubStaff;
use App\Models\LogisticsRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LogisticsCompleteSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Update Seller Addresses & Contacts
        $sellerAddresses = [
            'techzone.ph.seller@gmail.com' => [
                'address' => 'TechZone Warehouse, 123 Katipunan Ave, Quezon City, Metro Manila',
                'contact_no' => '09171112233',
                'province' => 'Metro Manila',
                'municipality' => 'Quezon City',
                'barangay' => 'Loyola Heights',
            ],
            'bellamoda.ph.seller@gmail.com' => [
                'address' => 'BellaModa Fashion Studio, 45 Ayala Ave, Makati City, Metro Manila',
                'contact_no' => '09172223344',
                'province' => 'Metro Manila',
                'municipality' => 'Makati City',
                'barangay' => 'San Lorenzo',
            ],
            'greenharvest.ph.seller@gmail.com' => [
                'address' => 'GreenHarvest Distribution Center, 78 Commonwealth Ave, Quezon City, Metro Manila',
                'contact_no' => '09173334455',
                'province' => 'Metro Manila',
                'municipality' => 'Quezon City',
                'barangay' => 'Matandang Balara',
            ],
            'glowhaven.ph.seller@gmail.com' => [
                'address' => 'GlowHaven Beauty Depot, 12 Shaw Blvd, Mandaluyong City, Metro Manila',
                'contact_no' => '09174445566',
                'province' => 'Metro Manila',
                'municipality' => 'Mandaluyong City',
                'barangay' => 'Wack-Wack',
            ],
            'apexhome.ph.seller@gmail.com' => [
                'address' => 'ApexHome Central Warehouse, 89 Ortigas Ave, Pasig City, Metro Manila',
                'contact_no' => '09175556677',
                'province' => 'Metro Manila',
                'municipality' => 'Pasig City',
                'barangay' => 'San Antonio',
            ],
        ];

        foreach ($sellerAddresses as $email => $data) {
            $seller = User::where('email', $email)->first();
            if ($seller) {
                $seller->update($data);
            }
        }
        $this->command->info('Seller collection locations and contacts updated.');

        // 2. Ensure Buyer default saved address for Jayvee Aguila
        $buyer = User::where('email', 'aguilajayvee85@gmail.com')->first();
        if ($buyer) {
            Address::firstOrCreate(
                ['user_id' => $buyer->id, 'is_default' => true],
                [
                    'label' => 'Home',
                    'recipient_name' => 'Jayvee Aguila',
                    'recipient_phone' => '09179998877',
                    'region' => 'National Capital Region',
                    'province' => 'Metro Manila',
                    'municipality' => 'Quezon City',
                    'barangay' => 'Loyola Heights',
                    'street_address' => '123 Katipunan Ave, Unit 4B',
                    'postal_code' => '1108',
                    'address' => '123 Katipunan Ave, Unit 4B, Loyola Heights, Quezon City, Metro Manila, 1108',
                ]
            );
        }

        // 3. Logistics Organization (Sorting Center)
        $logisticsUser = User::firstOrCreate(
            ['email' => 'logistics@shopalona.online'],
            [
                'name' => 'Alona Express Logistics Head',
                'password' => Hash::make('password'),
                'usertype' => 'sorting_center',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]
        );

        $center = SortingCenter::firstOrCreate(
            ['user_id' => $logisticsUser->id],
            [
                'business_name' => 'Alona Express Logistics',
                'approval_status' => 'approved',
            ]
        );
        $this->command->info("Sorting Center: {$center->business_name} (logistics@shopalona.online)");

        // 4. Logistics Hubs
        $manilaHub = LogisticsHub::firstOrCreate(
            ['sorting_center_id' => $center->id, 'code' => 'MNL-01'],
            [
                'name' => 'Central Manila Hub',
                'address' => '100 EDSA, Balintawak, Quezon City, Metro Manila',
                'city_code' => '1381300000',
                'pickup_city_codes' => ['1381300000', '1380700000', '1380600000'],
                'delivery_city_codes' => ['1381300000', '1380700000', '1380600000'],
                'is_active' => true,
            ]
        );

        $cebuHub = LogisticsHub::firstOrCreate(
            ['sorting_center_id' => $center->id, 'code' => 'CEB-01'],
            [
                'name' => 'Cebu Regional Hub',
                'address' => '200 Osmeña Blvd, Cebu City, Cebu',
                'city_code' => '0730600000',
                'pickup_city_codes' => ['0730600000'],
                'delivery_city_codes' => ['0730600000'],
                'is_active' => true,
            ]
        );

        // Logistics Hub Route
        LogisticsRoute::firstOrCreate(
            ['sorting_center_id' => $center->id, 'origin_hub_id' => $manilaHub->id, 'destination_hub_id' => $cebuHub->id],
            ['transport_mode' => 'mixed', 'is_active' => true]
        );
        $this->command->info('Logistics Hubs (MNL-01 and CEB-01) created.');

        // 5. Hub Areas
        $qcArea = Area::firstOrCreate(
            ['sorting_center_id' => $center->id, 'name' => 'Quezon City'],
            ['city_code' => '1381300000']
        );
        $makatiArea = Area::firstOrCreate(
            ['sorting_center_id' => $center->id, 'name' => 'Makati City'],
            ['city_code' => '1380700000']
        );
        $cebuArea = Area::firstOrCreate(
            ['sorting_center_id' => $center->id, 'name' => 'Cebu City'],
            ['city_code' => '0730600000']
        );

        // 6. Hub Staff
        $staffUser = User::firstOrCreate(
            ['email' => 'staff@shopalona.online'],
            [
                'name' => 'Hub Staff Desk',
                'password' => Hash::make('password'),
                'usertype' => 'sorting_center',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]
        );

        LogisticsHubStaff::firstOrCreate(
            ['user_id' => $staffUser->id],
            [
                'sorting_center_id' => $center->id,
                'logistics_hub_id' => $manilaHub->id,
                'is_active' => true,
            ]
        );
        $this->command->info('Hub Staff (staff@shopalona.online) attached to Manila Hub.');

        // 7. Riders
        $rider1User = User::firstOrCreate(
            ['email' => 'rider@shopalona.online'],
            [
                'name' => 'Juan Rider',
                'password' => Hash::make('password'),
                'usertype' => 'rider',
                'status' => 'approved',
                'contact_no' => '09171234567',
                'email_verified_at' => now(),
            ]
        );

        $rider1 = Rider::firstOrCreate(
            ['user_id' => $rider1User->id],
            [
                'sorting_center_id' => $center->id,
                'logistics_hub_id' => $manilaHub->id,
                'name' => 'Juan Rider',
                'contact' => '09171234567',
                'vehicle_type' => 'motorcycle',
                'plate_number' => 'MC-9988',
                'approval_status' => 'approved',
                'is_active' => true,
            ]
        );
        $rider1->areas()->syncWithoutDetaching([$qcArea->id, $makatiArea->id]);
        app(\App\Services\RiderPresence::class)->command($rider1, 'go_online', 'seeder');

        $rider2User = User::firstOrCreate(
            ['email' => 'rider2@shopalona.online'],
            [
                'name' => 'Pedro Rider',
                'password' => Hash::make('password'),
                'usertype' => 'rider',
                'status' => 'approved',
                'contact_no' => '09177654321',
                'email_verified_at' => now(),
            ]
        );

        $rider2 = Rider::firstOrCreate(
            ['user_id' => $rider2User->id],
            [
                'sorting_center_id' => $center->id,
                'logistics_hub_id' => $cebuHub->id,
                'name' => 'Pedro Rider',
                'contact' => '09177654321',
                'vehicle_type' => 'motorcycle',
                'plate_number' => 'CEB-5544',
                'approval_status' => 'approved',
                'is_active' => true,
            ]
        );
        $rider2->areas()->syncWithoutDetaching([$cebuArea->id]);
        app(\App\Services\RiderPresence::class)->command($rider2, 'go_online', 'seeder');
        $this->command->info('Riders (rider@shopalona.online & rider2@shopalona.online) ready and online.');

        // 8. Sample Deliveries and Orders
        $buyer = User::where('usertype', 'buyer')->first();
        $sampleProducts = Product::where('status', 'approved')->take(5)->get();

        if ($sampleProducts->count() >= 3 && $buyer) {
            // Delete old sample orders and child records if re-seeding
            $sampleOrders = Order::where('buyer_note', 'SEEDER_SAMPLE')->get();
            if ($sampleOrders->isNotEmpty()) {
                $orderIds = $sampleOrders->pluck('id')->all();
                $parcels = Parcel::whereIn('order_id', $orderIds)->get();
                $parcelIds = $parcels->pluck('id')->all();

                PickupRequest::whereIn('parcel_id', $parcelIds)->delete();
                Parcel::whereIn('id', $parcelIds)->delete();
                OrderItem::whereIn('order_id', $orderIds)->delete();
                Order::whereIn('id', $orderIds)->delete();
            }

            // Order A: OUT_FOR_DELIVERY (Assigned to Juan Rider)
            $prodA = $sampleProducts[0];
            $orderA = Order::create([
                'user_id' => $buyer->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'subtotal' => $prodA->price,
                'total' => $prodA->price + 50.00,
                'shipping_address' => 'Unit 12B High-Rise Condominium, Katipunan Ave, Quezon City, Metro Manila',
                'shipping_method' => 'standard',
                'shipping_fee' => 50.00,
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'status' => 'out_for_delivery',
                'buyer_note' => 'SEEDER_SAMPLE',
            ]);

            $itemA = OrderItem::create([
                'order_id' => $orderA->id,
                'product_id' => $prodA->id,
                'product_name' => $prodA->name,
                'price' => $prodA->price,
                'quantity' => 1,
                'status' => 'out_for_delivery',
            ]);

            Parcel::create([
                'order_id' => $orderA->id,
                'order_item_id' => $itemA->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $prodA->seller_id,
                'sorting_center_id' => $center->id,
                'status' => 'OUT_FOR_DELIVERY',
                'collection_address' => $prodA->seller?->address ?: 'Seller Warehouse, Quezon City',
                'delivery_address' => $orderA->shipping_address,
                'delivery_province' => 'Metro Manila',
                'delivery_municipality' => 'Quezon City',
                'delivery_barangay' => 'Loyola Heights',
                'delivery_street' => 'Katipunan Ave',
                'sorted_area_id' => $qcArea->id,
                'assigned_rider_id' => $rider1->id,
                'assigned_at' => now()->subHours(1),
            ]);

            // Order B: OUT_FOR_DELIVERY (Assigned to Juan Rider)
            $prodB = $sampleProducts[1];
            $orderB = Order::create([
                'user_id' => $buyer->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'subtotal' => $prodB->price,
                'total' => $prodB->price + 50.00,
                'shipping_address' => 'Tower 2 Ayala Avenue, Makati City, Metro Manila',
                'shipping_method' => 'standard',
                'shipping_fee' => 50.00,
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'status' => 'out_for_delivery',
                'buyer_note' => 'SEEDER_SAMPLE',
            ]);

            $itemB = OrderItem::create([
                'order_id' => $orderB->id,
                'product_id' => $prodB->id,
                'product_name' => $prodB->name,
                'price' => $prodB->price,
                'quantity' => 1,
                'status' => 'out_for_delivery',
            ]);

            Parcel::create([
                'order_id' => $orderB->id,
                'order_item_id' => $itemB->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $prodB->seller_id,
                'sorting_center_id' => $center->id,
                'status' => 'OUT_FOR_DELIVERY',
                'collection_address' => $prodB->seller?->address ?: 'Seller Warehouse, Makati City',
                'delivery_address' => $orderB->shipping_address,
                'delivery_province' => 'Metro Manila',
                'delivery_municipality' => 'Makati City',
                'delivery_barangay' => 'San Lorenzo',
                'delivery_street' => 'Ayala Ave',
                'sorted_area_id' => $makatiArea->id,
                'assigned_rider_id' => $rider1->id,
                'assigned_at' => now()->subHours(2),
            ]);

            // Order C: READY_FOR_PICKUP (Pending Logistics assignment)
            $prodC = $sampleProducts[2];
            $orderC = Order::create([
                'user_id' => $buyer->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'subtotal' => $prodC->price,
                'total' => $prodC->price + 50.00,
                'shipping_address' => '500 Commonwealth Ave, Quezon City, Metro Manila',
                'shipping_method' => 'standard',
                'shipping_fee' => 50.00,
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'status' => 'ready_for_pickup',
                'buyer_note' => 'SEEDER_SAMPLE',
            ]);

            $itemC = OrderItem::create([
                'order_id' => $orderC->id,
                'product_id' => $prodC->id,
                'product_name' => $prodC->name,
                'price' => $prodC->price,
                'quantity' => 1,
                'status' => 'ready_for_pickup',
            ]);

            $parcelC = Parcel::create([
                'order_id' => $orderC->id,
                'order_item_id' => $itemC->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $prodC->seller_id,
                'sorting_center_id' => $center->id,
                'status' => 'PLACED',
                'collection_address' => $prodC->seller?->address ?: 'Seller Warehouse, Quezon City',
                'delivery_address' => $orderC->shipping_address,
                'delivery_province' => 'Metro Manila',
                'delivery_municipality' => 'Quezon City',
                'delivery_barangay' => 'Commonwealth',
                'delivery_street' => 'Commonwealth Ave',
            ]);

            PickupRequest::create([
                'parcel_id' => $parcelC->id,
                'seller_id' => $prodC->seller_id,
                'sorting_center_id' => $center->id,
                'status' => 'pending',
            ]);

            $this->command->info('Active sample parcels (Out for Delivery and Ready for Pickup) seeded for Rider Mobile and Hub Desk testing.');
        }
    }
}
