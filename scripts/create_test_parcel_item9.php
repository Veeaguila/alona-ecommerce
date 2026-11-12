<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Area, Category, LogisticsHub, LogisticsRoute, Order, OrderItem, Parcel, ParcelHubJourney, Product, Rider, SortingCenter, User};
use Illuminate\Support\Str;

$center = SortingCenter::firstOrFail();
$manilaHub = LogisticsHub::where('sorting_center_id', $center->id)->where('name', 'like', '%Manila%')->firstOrFail();
$lagunaHub = LogisticsHub::where('sorting_center_id', $center->id)->where('name', 'like', '%Laguna%')->firstOrFail();

$seller = User::where('usertype', 'seller')->first() ?? User::factory()->create(['usertype' => 'seller', 'status' => 'approved', 'address' => '104 MacArthur Highway, Barangay 1, City of Caloocan, Metro Manila']);
$buyer = User::where('usertype', 'buyer')->first() ?? User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);

$category = Category::firstOrCreate(['slug' => 'food-delicacies'], ['name' => 'Food & Delicacies', 'is_active' => true]);

$product = Product::firstOrCreate(['name' => 'Fresh Laguna Buko Pie 500g'], [
    'seller_id' => $seller->id,
    'category_id' => $category->id,
    'slug' => 'fresh-laguna-buko-pie',
    'price' => 250,
    'stock' => 50,
    'status' => 'approved',
]);

$orderNumber = 'ALN-' . date('Ymd') . '-' . rand(100000, 999999);
$order = Order::create([
    'user_id' => $buyer->id,
    'order_number' => $orderNumber,
    'status' => 'processing',
    'shipping_address' => 'Poblacion, Calamba City, Laguna',
    'payment_method' => 'cod',
    'payment_status' => 'pending',
    'subtotal' => 250,
    'shipping_fee' => 75,
    'total' => 325,
]);

$orderItemId = OrderItem::max('id') + 1;
$item = OrderItem::create([
    'id' => $orderItemId,
    'order_id' => $order->id,
    'product_id' => $product->id,
    'product_name' => $product->name,
    'quantity' => 1,
    'price' => 250,
    'status' => 'ready_for_pickup',
    'tracking_number' => 'TRK-' . date('Ymd') . '-' . rand(1000, 9999),
    'courier_name' => $center->business_name,
]);

$parcelId = Parcel::max('id') + 1;
$orderIdString = 'ITEM-' . $orderItemId;

$parcel = Parcel::create([
    'id' => $parcelId,
    'order_id' => $orderIdString,
    'order_item_id' => $item->id,
    'sorting_center_id' => $center->id,
    'buyer_id' => $buyer->id,
    'seller_id' => $seller->id,
    'status' => 'PLACED',
    'collection_address' => '104 MacArthur Highway, Barangay 1, City of Caloocan, Metro Manila',
    'delivery_address' => 'Poblacion, Calamba City, Laguna',
    'delivery_province' => 'Laguna',
    'delivery_municipality' => 'Calamba',
    'delivery_barangay' => 'Poblacion',
    'delivery_street' => 'Rizal Street',
    'delivery_house_number' => '42',
]);

ParcelHubJourney::create([
    'parcel_id' => $parcel->id,
    'sorting_center_id' => $center->id,
    'origin_hub_id' => $manilaHub->id,
    'destination_hub_id' => $lagunaHub->id,
    'current_hub_id' => $manilaHub->id,
    'route_snapshot' => [
        'company_name' => $center->business_name,
        'origin_hub_id' => $manilaHub->id,
        'origin_hub_name' => $manilaHub->name,
        'destination_hub_id' => $lagunaHub->id,
        'destination_hub_name' => $lagunaHub->name,
        'origin_city_code' => '',
        'destination_city_code' => '',
        'transport_mode' => 'road',
    ],
]);

echo "Created test parcel:\n";
echo "Parcel ID: {$parcel->id}\n";
echo "Order ID (Barcode Reference): {$parcel->order_id}\n";
echo "Tracking Number: {$item->tracking_number}\n";
echo "Order Number: {$order->order_number}\n";
echo "Status: {$parcel->status} (Awaiting store pickup)\n";
echo "Origin Hub: {$manilaHub->name}\n";
echo "Destination Hub: {$lagunaHub->name}\n";
echo "Payment: COD ₱325.00\n";
