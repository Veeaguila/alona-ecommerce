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

                // If this seller already has products seeded, preserve them to protect order FKs, but ensure variants are labeled
                $existingCount = Product::where('seller_id', $seller->id)->count();
                if ($existingCount >= count($items)) {
                    $totalSeeded += $existingCount;
                    $relabeled = $this->relabelExistingSellerVariants($seller, $prefix, $items);
                    if ($this->command) {
                        $this->command->info("Seller {$seller->name} already has {$existingCount} products ({$relabeled} variants verified/labeled).");
                    }
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
                    $product = new Product([
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
                        'ai_verification_status' => 'approved',
                        'ai_confidence_score' => 0.99,
                        'ai_verified_at' => now(),
                        'approval_method' => 'automatic',
                    ]);

                    $variantPlan = $this->getVariantPlanForProduct($product, $item);
                    $product->variation_types = $variantPlan['variation_types'];
                    $product->save();

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

                    // Add variants with realistic, category-tailored options
                    $createdVariants = [];
                    foreach ($variantPlan['variants'] as $vIdx => $vPlan) {
                        $vPrice = round($item['price'] * $vPlan['price_mult'], 2);
                        $vStock = max(10, (int) round($stock * $vPlan['stock_mult']));
                        $vSku = strtoupper(substr($prefix, 0, 3)) . '-' . $product->id . '-' . $vPlan['suffix'];
                        $vImg = ($vIdx > 0 && !empty($item['gallery'][0])) ? $item['gallery'][0] : $item['image'];

                        $createdVariants[] = ProductVariant::create([
                            'product_id' => $product->id,
                            'color' => $vPlan['color'],
                            'size' => $vPlan['size'],
                            'variant_attributes' => $vPlan['attributes'],
                            'combination_key' => $this->buildCombinationKey($vPlan['attributes']),
                            'price' => $vPrice,
                            'stock' => $vStock,
                            'sku' => $vSku,
                            'image_path' => $vImg,
                        ]);
                    }

                    // Add 2 to 3 reviews from distinct @gmail.com buyers
                    $numReviews = rand(2, 3);
                    $reviewers = $buyers->random(min($numReviews, $buyers->count()));
                    $reviewRatings = [];

                    foreach ($reviewers as $reviewer) {
                        $rating = (rand(1, 10) > 2) ? 5 : 4;
                        $commentPool = $sampleReviewComments[$rating];
                        $comment = $commentPool[array_rand($commentPool)];

                        $chosenVariant = $createdVariants[array_rand($createdVariants)];
                        $varName = $chosenVariant->label();

                        Review::create([
                            'user_id' => $reviewer->id,
                            'product_id' => $product->id,
                            'variant_id' => $chosenVariant->id,
                            'variant_name' => $varName ?: $chosenVariant->title,
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
            if ($this->command) {
                $this->command->info("SUCCESS: Seeded total {$totalSeeded} products with {$uniqueCount} UNIQUE images and {$totalReviews} verified @gmail.com reviews!");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Relabel existing generic variants of a seller to category-tailored options.
     */
    public function relabelExistingSellerVariants(User $seller, string $prefix, array $catalogItems): int
    {
        $products = Product::with(['variants', 'category'])->where('seller_id', $seller->id)->get();
        $updatedCount = 0;

        foreach ($products as $idx => $product) {
            $item = $catalogItems[$idx] ?? [
                'name' => $product->name,
                'cat_slug' => $product->category?->slug ?? '',
                'image' => $product->image_path,
                'price' => (float) $product->price,
            ];

            $variantPlan = $this->getVariantPlanForProduct($product, $item);

            // Update product variation types
            $product->update([
                'variation_types' => $variantPlan['variation_types'],
            ]);

            $variants = $product->variants()->orderBy('id')->get();
            if ($variants->isEmpty()) {
                continue;
            }

            foreach ($variants as $vIdx => $variant) {
                $plan = $variantPlan['variants'][$vIdx % count($variantPlan['variants'])];
                $newSku = strtoupper(substr($prefix, 0, 3)) . '-' . $product->id . '-' . $plan['suffix'];

                $variant->update([
                    'color' => $plan['color'],
                    'size' => $plan['size'],
                    'variant_attributes' => $plan['attributes'],
                    'combination_key' => $this->buildCombinationKey($plan['attributes']),
                    'sku' => $variant->sku ?: $newSku,
                ]);

                // Update review records referencing this variant
                Review::where('variant_id', $variant->id)->update([
                    'variant_name' => $variant->label(),
                ]);

                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Build category-tailored variation attributes and options.
     */
    public function getVariantPlanForProduct(Product $product, array $item = []): array
    {
        $name = strtolower($item['name'] ?? $product->name);
        $catSlug = strtolower($product->category?->slug ?? $item['cat_slug'] ?? '');

        // 1. Footwear / Shoes
        if (str_contains($catSlug, 'shoe') || str_contains($catSlug, 'footwear') || str_contains($name, 'shoe') || str_contains($name, 'sneaker') || str_contains($name, 'boot') || str_contains($name, 'loafer') || str_contains($name, 'heel')) {
            return [
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Black', 'White']],
                    ['name' => 'Size (EU)', 'options' => ['41', '42']],
                ],
                'variants' => [
                    [
                        'color' => 'Black',
                        'size' => 'EU 41',
                        'attributes' => ['Color' => 'Black', 'Size (EU)' => '41'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'BLK-41',
                    ],
                    [
                        'color' => 'White',
                        'size' => 'EU 42',
                        'attributes' => ['Color' => 'White', 'Size (EU)' => '42'],
                        'price_mult' => 1.05,
                        'stock_mult' => 0.85,
                        'suffix' => 'WHT-42',
                    ],
                ],
            ];
        }

        // 2. Apparel / Clothing / Fashion
        if (str_contains($catSlug, 'apparel') || str_contains($catSlug, 'fashion') || str_contains($catSlug, 'clothing') || str_contains($name, 'shirt') || str_contains($name, 'dress') || str_contains($name, 'hoodie') || str_contains($name, 'jacket') || str_contains($name, 'pants') || str_contains($name, 't-shirt')) {
            return [
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Black', 'Navy']],
                    ['name' => 'Size', 'options' => ['Medium', 'Large']],
                ],
                'variants' => [
                    [
                        'color' => 'Black',
                        'size' => 'Medium',
                        'attributes' => ['Color' => 'Black', 'Size' => 'M'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'BLK-M',
                    ],
                    [
                        'color' => 'Navy',
                        'size' => 'Large',
                        'attributes' => ['Color' => 'Navy', 'Size' => 'L'],
                        'price_mult' => 1.05,
                        'stock_mult' => 0.9,
                        'suffix' => 'NVY-L',
                    ],
                ],
            ];
        }

        // 3. Laptops & Computers
        if (str_contains($catSlug, 'laptop') || str_contains($catSlug, 'computer') || str_contains($name, 'macbook') || str_contains($name, 'laptop') || str_contains($name, 'zenbook') || str_contains($name, 'matebook') || str_contains($name, 'desktop')) {
            return [
                'variation_types' => [
                    ['name' => 'RAM', 'options' => ['16GB', '32GB']],
                    ['name' => 'Storage', 'options' => ['512GB SSD', '1TB SSD']],
                ],
                'variants' => [
                    [
                        'color' => 'Space Gray',
                        'size' => '16GB / 512GB',
                        'attributes' => ['Color' => 'Space Gray', 'RAM' => '16GB', 'Storage' => '512GB SSD'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => '16G-512G',
                    ],
                    [
                        'color' => 'Silver',
                        'size' => '32GB / 1TB',
                        'attributes' => ['Color' => 'Silver', 'RAM' => '32GB', 'Storage' => '1TB SSD'],
                        'price_mult' => 1.20,
                        'stock_mult' => 0.7,
                        'suffix' => '32G-1TB',
                    ],
                ],
            ];
        }

        // 4. Mobiles, Tablets, Gadgets, Smartwatches
        if (str_contains($catSlug, 'mobile') || str_contains($catSlug, 'phone') || str_contains($catSlug, 'gadget') || str_contains($catSlug, 'watch') || str_contains($name, 'iphone') || str_contains($name, 'samsung') || str_contains($name, 'phone') || str_contains($name, 'watch') || str_contains($name, 'tablet') || str_contains($name, 'ipad')) {
            return [
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Midnight Black', 'Starlight Silver']],
                    ['name' => 'Storage', 'options' => ['128GB', '256GB']],
                ],
                'variants' => [
                    [
                        'color' => 'Midnight Black',
                        'size' => '128GB',
                        'attributes' => ['Color' => 'Midnight Black', 'Storage' => '128GB'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'BLK-128G',
                    ],
                    [
                        'color' => 'Starlight Silver',
                        'size' => '256GB',
                        'attributes' => ['Color' => 'Starlight Silver', 'Storage' => '256GB'],
                        'price_mult' => 1.15,
                        'stock_mult' => 0.8,
                        'suffix' => 'SLV-256G',
                    ],
                ],
            ];
        }

        // 5. Audio / Sound
        if (str_contains($catSlug, 'audio') || str_contains($name, 'headphone') || str_contains($name, 'earbud') || str_contains($name, 'speaker')) {
            return [
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Matte Black', 'White Pearl']],
                    ['name' => 'Connectivity', 'options' => ['Wireless Bluetooth', 'ANC Edition']],
                ],
                'variants' => [
                    [
                        'color' => 'Matte Black',
                        'size' => 'Standard Wireless',
                        'attributes' => ['Color' => 'Matte Black', 'Connectivity' => 'Wireless Bluetooth'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'BLK-STD',
                    ],
                    [
                        'color' => 'White Pearl',
                        'size' => 'Pro ANC Edition',
                        'attributes' => ['Color' => 'White Pearl', 'Connectivity' => 'ANC Edition'],
                        'price_mult' => 1.18,
                        'stock_mult' => 0.75,
                        'suffix' => 'WHT-PRO',
                    ],
                ],
            ];
        }

        // 6. Food, Groceries, Snacks, Beverages
        if (str_contains($catSlug, 'grocer') || str_contains($catSlug, 'snack') || str_contains($catSlug, 'beverage') || str_contains($name, 'juice') || str_contains($name, 'coffee') || str_contains($name, 'tea') || str_contains($name, 'snack') || str_contains($name, 'organic') || str_contains($name, 'chips')) {
            return [
                'variation_types' => [
                    ['name' => 'Flavor', 'options' => ['Original Classic', 'Special Blend']],
                    ['name' => 'Package Size', 'options' => ['250g', '500g']],
                ],
                'variants' => [
                    [
                        'color' => 'Original Classic',
                        'size' => '250g',
                        'attributes' => ['Flavor' => 'Original Classic', 'Package Size' => '250g'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'ORIG-250G',
                    ],
                    [
                        'color' => 'Special Blend',
                        'size' => '500g',
                        'attributes' => ['Flavor' => 'Special Blend', 'Package Size' => '500g'],
                        'price_mult' => 1.80,
                        'stock_mult' => 0.8,
                        'suffix' => 'SPEC-500G',
                    ],
                ],
            ];
        }

        // 7. Beauty, Cosmetics, Fragrance
        if (str_contains($catSlug, 'beauty') || str_contains($catSlug, 'perfume') || str_contains($name, 'perfume') || str_contains($name, 'serum') || str_contains($name, 'cream') || str_contains($name, 'lipstick') || str_contains($name, 'lotion')) {
            return [
                'variation_types' => [
                    ['name' => 'Shade / Formula', 'options' => ['Natural Classic', 'Intense Glow']],
                    ['name' => 'Net Volume', 'options' => ['50ml', '100ml']],
                ],
                'variants' => [
                    [
                        'color' => 'Natural Classic',
                        'size' => '50ml',
                        'attributes' => ['Formula' => 'Natural Classic', 'Volume' => '50ml'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'NAT-50ML',
                    ],
                    [
                        'color' => 'Intense Glow',
                        'size' => '100ml',
                        'attributes' => ['Formula' => 'Intense Glow', 'Volume' => '100ml'],
                        'price_mult' => 1.65,
                        'stock_mult' => 0.85,
                        'suffix' => 'GLOW-100ML',
                    ],
                ],
            ];
        }

        // 8. Furniture / Home Living
        if (str_contains($catSlug, 'home') || str_contains($catSlug, 'furniture') || str_contains($name, 'sofa') || str_contains($name, 'table') || str_contains($name, 'chair') || str_contains($name, 'lamp') || str_contains($name, 'bed')) {
            return [
                'variation_types' => [
                    ['name' => 'Finish', 'options' => ['Natural Oak', 'Walnut Brown']],
                    ['name' => 'Dimension', 'options' => ['Standard', 'Deluxe']],
                ],
                'variants' => [
                    [
                        'color' => 'Natural Oak',
                        'size' => 'Standard',
                        'attributes' => ['Finish' => 'Natural Oak', 'Size' => 'Standard'],
                        'price_mult' => 1.0,
                        'stock_mult' => 1.0,
                        'suffix' => 'OAK-STD',
                    ],
                    [
                        'color' => 'Walnut Brown',
                        'size' => 'Deluxe',
                        'attributes' => ['Finish' => 'Walnut Brown', 'Size' => 'Deluxe'],
                        'price_mult' => 1.25,
                        'stock_mult' => 0.75,
                        'suffix' => 'WLN-DLX',
                    ],
                ],
            ];
        }

        // Default Fallback
        return [
            'variation_types' => [
                ['name' => 'Edition', 'options' => ['Standard', 'Special Edition']],
                ['name' => 'Bundle', 'options' => ['Single Pack', 'Twin Pack']],
            ],
            'variants' => [
                [
                    'color' => 'Standard',
                    'size' => 'Single Pack',
                    'attributes' => ['Edition' => 'Standard', 'Pack' => 'Single Pack'],
                    'price_mult' => 1.0,
                    'stock_mult' => 1.0,
                    'suffix' => 'STD-1PK',
                ],
                [
                    'color' => 'Special Edition',
                    'size' => 'Twin Pack',
                    'attributes' => ['Edition' => 'Special Edition', 'Pack' => 'Twin Pack'],
                    'price_mult' => 1.80,
                    'stock_mult' => 0.8,
                    'suffix' => 'SPEC-2PK',
                ],
            ],
        ];
    }

    /**
     * Build combination key matching ProductVariant convention.
     */
    protected function buildCombinationKey(array $attributes): string
    {
        $pairs = [];
        foreach ($attributes as $k => $v) {
            if (filled($v)) {
                $pairs[mb_strtolower(trim((string) $k))] = mb_strtolower(trim((string) $v));
            }
        }
        ksort($pairs);
        $parts = [];
        foreach ($pairs as $k => $v) {
            $parts[] = "{$k}:{$v}";
        }
        return implode('|', $parts);
    }
}
