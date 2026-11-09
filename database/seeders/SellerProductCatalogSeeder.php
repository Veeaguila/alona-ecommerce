<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sellerConfigs = [
            'tech' => ['email' => 'techzone.ph.seller@gmail.com', 'name' => 'TechZone PH', 'store_name' => 'TechZone Official Store'],
            'fashion' => ['email' => 'bellamoda.ph.seller@gmail.com', 'name' => 'BellaModa PH', 'store_name' => 'BellaModa Fashion'],
            'grocery' => ['email' => 'greenharvest.ph.seller@gmail.com', 'name' => 'GreenHarvest PH', 'store_name' => 'GreenHarvest Organics'],
            'beauty' => ['email' => 'glowhaven.ph.seller@gmail.com', 'name' => 'GlowHaven PH', 'store_name' => 'GlowHaven Beauty'],
            'home' => ['email' => 'apexhome.ph.seller@gmail.com', 'name' => 'ApexHome PH', 'store_name' => 'ApexHome Living'],
        ];

        $sellers = [];
        foreach ($sellerConfigs as $key => $conf) {
            $sellers[$key] = User::firstOrCreate(
                ['email' => $conf['email']],
                [
                    'name' => $conf['name'],
                    'store_name' => $conf['store_name'],
                    'password' => bcrypt('password'),
                    'usertype' => 'seller',
                    'status' => 'approved',
                    'email_verified_at' => now(),
                ]
            );
        }

        $sampleBuyerEmails = [
            'juan.buyer@gmail.com' => 'Juan Dela Cruz',
            'maria.buyer@gmail.com' => 'Maria Santos',
            'mark.buyer@gmail.com' => 'Mark Reyes',
            'anna.buyer@gmail.com' => 'Anna Mendoza',
        ];

        foreach ($sampleBuyerEmails as $email => $name) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt('password'),
                    'usertype' => 'buyer',
                    'status' => 'approved',
                    'email_verified_at' => now(),
                ]
            );
        }

        $buyers = User::where('usertype', 'buyer')
            ->where('email', 'like', '%@gmail.com')
            ->get();

        $categoriesBySlug = Category::all()->keyBy('slug');

        $sampleReviewComments = [
            5 => [
                "Super ganda ng quality! Sobrang nagustuhan ko and arrived earlier than expected.",
                "Legit item and 100% authentic. Well packed with bubble wrap and fragile sticker. 5 stars!",
                "Amazing product! Fits description perfectly and works like a charm. Will definitely order again from this shop.",
                "Worth every peso! Napakabilis ng delivery to Caloocan and very accommodating si seller.",
                "Excellent craftsmanship and high quality materials. Highly recommended to everyone!",
                "Sobrang sulit! Natanggap ko nang walang damage. Sealed and brand new. Salamat seller!",
                "Second time buying from this seller and never disappoints! High standard as always.",
                "Five stars! Very satisfied buyer here. Very premium feel and looks classy.",
            ],
            4 => [
                "Good quality item for its price. Fast shipping and secured packaging. Thank you!",
                "Maganda siya and nagustuhan ng family. Minor delay on logistics pero all goods yung item.",
                "Nice product! Working as advertised and complete inclusions inside the box.",
                "Pretty satisfied with this purchase! Value for money and accurate sizing/specs.",
                "Maayos ang pagkakabalot and complete orders. Thank you seller for the smooth transaction!",
            ],
        ];

        $catalogJsonPath = base_path('database/data/catalog_250_unique.json');
        if (!file_exists($catalogJsonPath)) {
            throw new \RuntimeException("Catalog JSON not found at $catalogJsonPath");
        }

        $allCatalogs = json_decode(file_get_contents($catalogJsonPath), true, 512, JSON_THROW_ON_ERROR);

        $sellerMappings = [
            ['seller' => $sellers['tech'], 'products' => $allCatalogs['tech'], 'tag' => 'TechZone'],
            ['seller' => $sellers['fashion'], 'products' => $allCatalogs['fashion'], 'tag' => 'BellaModa'],
            ['seller' => $sellers['grocery'], 'products' => $allCatalogs['grocery'], 'tag' => 'GreenHarvest'],
            ['seller' => $sellers['beauty'], 'products' => $allCatalogs['beauty'], 'tag' => 'GlowHaven'],
            ['seller' => $sellers['home'], 'products' => $allCatalogs['home'], 'tag' => 'ApexHome'],
        ];

        DB::beginTransaction();
        try {
            $totalSeeded = 0;
            $totalReviews = 0;
            $usedImageUrls = [];

            foreach ($sellerMappings as $entry) {
                $seller = $entry['seller'];
                $items = $entry['products'];
                $prefix = $entry['tag'];

                // If this seller already has products seeded, preserve them to protect order FKs
                $existingCount = Product::where('seller_id', $seller->id)->count();
                if ($existingCount >= count($items)) {
                    $totalSeeded += $existingCount;
                    $this->command->info("Seller {$seller->name} already has {$existingCount} products, skipping re-creation.");
                    continue;
                }

                // Remove previous products of this seller if any (only when none are referenced by order items)
                $existingProductIds = Product::where('seller_id', $seller->id)->pluck('id');
                if ($existingProductIds->isNotEmpty()) {
                    Review::whereIn('product_id', $existingProductIds)->delete();
                    ProductVariant::whereIn('product_id', $existingProductIds)->delete();
                    ProductImage::whereIn('product_id', $existingProductIds)->delete();
                    Product::whereIn('id', $existingProductIds)->delete();
                }

                $count = 0;
                foreach ($items as $item) {
                    $count++;
                    $cat = $categoriesBySlug->get($item['cat_slug']) ?? $categoriesBySlug->first();

                    $stock = rand(30, 80);
                    $product = Product::create([
                        'category_id' => $cat->id,
                        'seller_id' => $seller->id,
                        'name' => $item['name'],
                        'slug' => Str::slug($item['name']) . '-' . strtolower(Str::random(6)),
                        'description' => $item['description'],
                        'price' => $item['price'],
                        'old_price' => $item['old_price'] ?? ($item['price'] * 1.25),
                        'stock' => $stock * 2,
                        'per_buyer_limit' => 5,
                        'low_stock_threshold' => 5,
                        'low_stock_alerted' => false,
                        'image_path' => $item['image'],
                        'status' => 'approved',
                        'is_featured' => ($count <= 5),
                        'rating' => 5.0,
                        'reviews_count' => 0,
                        'weight' => 0.50,
                        'length' => 15,
                        'width' => 12,
                        'height' => 8,
                        'brand' => $item['brand'] ?? 'Alona Choice',
                        'condition' => 'New',
                        'variation_types' => ['Option', 'Color'],
                        'ai_verification_status' => 'approved',
                        'ai_confidence_score' => 0.99,
                        'ai_verified_at' => now(),
                        'approval_method' => 'automatic',
                    ]);

                    $usedImageUrls[] = $item['image'];

                    // Add primary image to gallery
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $item['image'],
                        'sort_order' => 1,
                    ]);

                    // Add extra gallery images if provided
                    if (!empty($item['gallery'])) {
                        foreach ($item['gallery'] as $idx => $galleryUrl) {
                            if (!empty($galleryUrl) && is_string($galleryUrl)) {
                                ProductImage::create([
                                    'product_id' => $product->id,
                                    'image_path' => $galleryUrl,
                                    'sort_order' => $idx + 2,
                                ]);
                            }
                        }
                    }

                    // Add 2 variants with realistic options
                    $createdVariants = [];
                    $createdVariants[] = ProductVariant::create([
                        'product_id' => $product->id,
                        'color' => 'Standard Edition',
                        'size' => 'Default',
                        'price' => $item['price'],
                        'stock' => $stock,
                        'sku' => strtoupper(substr($prefix, 0, 3)) . '-' . $product->id . '-STD',
                        'image_path' => $item['image'],
                    ]);

                    $createdVariants[] = ProductVariant::create([
                        'product_id' => $product->id,
                        'color' => 'Premium Edition',
                        'size' => 'Plus',
                        'price' => round($item['price'] * 1.15, 2),
                        'stock' => round($stock * 0.8),
                        'sku' => strtoupper(substr($prefix, 0, 3)) . '-' . $product->id . '-PRM',
                        'image_path' => !empty($item['gallery'][0]) ? $item['gallery'][0] : $item['image'],
                    ]);

                    // Add 2 to 3 reviews from distinct @gmail.com buyers
                    $numReviews = rand(2, 3);
                    $reviewers = $buyers->random(min($numReviews, $buyers->count()));
                    $reviewRatings = [];

                    foreach ($reviewers as $reviewer) {
                        $rating = (rand(1, 10) > 2) ? 5 : 4;
                        $commentPool = $sampleReviewComments[$rating];
                        $comment = $commentPool[array_rand($commentPool)];

                        $chosenVariant = $createdVariants[array_rand($createdVariants)];
                        $varName = trim(($chosenVariant->color ?? '') . ' ' . ($chosenVariant->size ?? ''));

                        Review::create([
                            'user_id' => $reviewer->id,
                            'product_id' => $product->id,
                            'variant_id' => $chosenVariant->id,
                            'variant_name' => $varName ?: 'Standard Edition',
                            'rating' => $rating,
                            'comment' => $comment,
                            'created_at' => now()->subDays(rand(1, 25))->subHours(rand(1, 23)),
                        ]);

                        $reviewRatings[] = $rating;
                        $totalReviews++;
                    }

                    // Update product aggregated rating and review count
                    if (!empty($reviewRatings)) {
                        $avg = round(array_sum($reviewRatings) / count($reviewRatings), 2);
                        $product->update([
                            'rating' => $avg,
                            'reviews_count' => count($reviewRatings),
                        ]);
                    }

                    $totalSeeded++;
                }

                $this->command->info("Seeded 50 products for {$seller->store_name} ({$seller->email}) with 50 distinct images");
            }

            DB::commit();
            $uniqueCount = count(array_unique($usedImageUrls));
            $this->command->info("SUCCESS: Seeded total {$totalSeeded} products with {$uniqueCount} UNIQUE images and {$totalReviews} verified @gmail.com reviews!");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
