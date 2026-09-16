<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$seller = User::updateOrCreate(
    ['email' => 'seller@example.com'],
    [
        'name' => 'Seller',
        'first_name' => 'Seller',
        'last_name' => 'Example',
        'usertype' => 'seller',
        'status' => 'approved',
        'password' => Hash::make('password'),
    ]
);

$buyer = User::updateOrCreate(
    ['email' => 'buyer@example.com'],
    [
        'name' => 'Buyer',
        'first_name' => 'Buyer',
        'last_name' => 'Example',
        'usertype' => 'buyer',
        'status' => 'approved',
        'password' => Hash::make('password'),
    ]
);

$product = Product::updateOrCreate(
    ['name' => 'Real DB Test Product'],
    [
        'seller_id' => $seller->id,
        'category_id' => 1,
        'slug' => 'real-db-test-product',
        'description' => 'Real database verification product',
        'price' => 1000.00,
        'old_price' => null,
        'stock' => 10,
        'image_path' => null,
        'status' => 'approved',
        'rating' => 0,
        'reviews_count' => 0,
    ]
);

$product->update(['price' => 1000.00, 'stock' => 10, 'status' => 'approved']);

echo json_encode([
    'seller' => ['id' => $seller->id, 'email' => $seller->email, 'usertype' => $seller->usertype, 'status' => $seller->status],
    'buyer' => ['id' => $buyer->id, 'email' => $buyer->email, 'usertype' => $buyer->usertype, 'status' => $buyer->status],
    'product' => [
        'id' => $product->id,
        'seller_id' => $product->seller_id,
        'category_id' => $product->category_id,
        'name' => $product->name,
        'price' => (string) $product->price,
        'stock' => $product->stock,
        'status' => $product->status,
    ],
], JSON_PRETTY_PRINT), PHP_EOL;
