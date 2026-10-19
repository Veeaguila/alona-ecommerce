<?php

namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller1;
    private User $buyer1;
    private Category $bagsCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller1 = User::factory()->create([
            'name' => 'Seller One',
            'email' => 'seller1@example.com',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Maria Handwoven Crafts',
        ]);

        $this->buyer1 = User::factory()->create([
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->bagsCategory = Category::create([
            'name' => 'Bags & Totes',
            'slug' => 'bags-totes',
            'is_active' => true,
        ]);
    }

    private function createFakePng(string $name = 'product.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 800);
    }

    private function validProductPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bicol Coin Purse',
            'category_id' => $this->bagsCategory->id,
            'description' => 'Woven coin purse in multiple colors and sizes.',
            'price' => 99.00,
            'stock' => 30,
            'weight' => 0.25,
            'status' => 'pending',
            'images' => [$this->createFakePng('coin-purse.png')],
        ], $overrides);
    }

    private function createP01Product(): Product
    {
        $product = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->bagsCategory->id,
            'name' => 'Handwoven Abaca Tote Bag',
            'slug' => 'handwoven-abaca-tote-bag',
            'description' => 'Handcrafted in Bicol from natural abaca fibers.',
            'price' => 199.00,
            'stock' => 30,
            'weight' => 0.50,
            'status' => 'approved',
            'image_path' => 'products/abaca-tote-main.png',
            'variation_types' => [
                ['name' => 'Color', 'options' => ['Natural', 'Black']],
                ['name' => 'Size', 'options' => ['S', 'M', 'L']],
            ],
        ]);

        $rows = [
            ['color' => 'Natural', 'size' => 'S', 'price' => 199.00, 'stock' => 5, 'sku' => 'MCH-TOTE-NAT-S', 'image_path' => 'products/variants/natural.png'],
            ['color' => 'Natural', 'size' => 'M', 'price' => 249.00, 'stock' => 5, 'sku' => 'MCH-TOTE-NAT-M', 'image_path' => 'products/variants/natural.png'],
            ['color' => 'Natural', 'size' => 'L', 'price' => 299.00, 'stock' => 5, 'sku' => 'MCH-TOTE-NAT-L', 'image_path' => 'products/variants/natural.png'],
            ['color' => 'Black', 'size' => 'S', 'price' => 199.00, 'stock' => 5, 'sku' => 'MCH-TOTE-BLK-S', 'image_path' => 'products/variants/black.png'],
            ['color' => 'Black', 'size' => 'M', 'price' => 249.00, 'stock' => 5, 'sku' => 'MCH-TOTE-BLK-M', 'image_path' => 'products/variants/black.png'],
            ['color' => 'Black', 'size' => 'L', 'price' => 299.00, 'stock' => 5, 'sku' => 'MCH-TOTE-BLK-L', 'image_path' => 'products/variants/black.png'],
        ];

        foreach ($rows as $row) {
            $product->variants()->create($row);
        }

        return $product->fresh('variants');
    }

    /**
     * SEL-VAR-001: Create a product with 2 variation types
     */
    public function test_sel_var_001_create_product_with_two_variation_types(): void
    {
        $variants = [
            ['color' => 'Red', 'size' => 'S', 'price' => 99.00, 'stock' => 5, 'sku' => 'BCP-RED-S'],
            ['color' => 'Red', 'size' => 'M', 'price' => 129.00, 'stock' => 5, 'sku' => 'BCP-RED-M'],
            ['color' => 'Red', 'size' => 'L', 'price' => 149.00, 'stock' => 5, 'sku' => 'BCP-RED-L'],
            ['color' => 'Blue', 'size' => 'S', 'price' => 99.00, 'stock' => 5, 'sku' => 'BCP-BLUE-S'],
            ['color' => 'Blue', 'size' => 'M', 'price' => 129.00, 'stock' => 5, 'sku' => 'BCP-BLUE-M'],
            ['color' => 'Blue', 'size' => 'L', 'price' => 149.00, 'stock' => 5, 'sku' => 'BCP-BLUE-L'],
        ];

        $response = $this->actingAs($this->seller1)->post(route('seller.products.store'), $this->validProductPayload([
            'variation_types' => [
                ['name' => 'Color', 'options' => ['Red', 'Blue']],
                ['name' => 'Size', 'options' => ['S', 'M', 'L']],
            ],
            'variants' => $variants,
        ]));

        $response->assertRedirect(route('seller.products'));
        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Bicol Coin Purse')->with('variants')->firstOrFail();
        $this->assertCount(6, $product->variants);
        $this->assertEquals(30, $product->stock);
        $this->assertEquals(99.00, (float) $product->price);

        foreach ($variants as $expected) {
            $this->assertDatabaseHas('product_variants', [
                'product_id' => $product->id,
                'color' => $expected['color'],
                'size' => $expected['size'],
                'price' => $expected['price'],
                'stock' => $expected['stock'],
                'sku' => $expected['sku'],
            ]);
        }

        // Approve product so Buyer 1 can view it
        $product->update(['status' => 'approved']);

        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $product->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.price_range', '₱99 – ₱149')
                ->has('product.variants', 6)
            );
    }

    /**
     * SEL-VAR-002: Add a third variation type -> Blocked with a message stating a maximum of 2 variation types
     */
    public function test_sel_var_002_add_third_variation_type_blocked(): void
    {
        $product = $this->createP01Product();
        $this->assertCount(6, $product->variants);

        $response = $this->actingAs($this->seller1)
            ->from(route('seller.products.edit', $product->id))
            ->patch(route('seller.products.update', $product->id), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'weight' => $product->weight,
                'status' => $product->status,
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Natural', 'Black']],
                    ['name' => 'Size', 'options' => ['S', 'M', 'L']],
                    ['name' => 'Material', 'options' => ['Abaca', 'Buri']],
                ],
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'color' => $v->color,
                    'size' => $v->size,
                    'price' => $v->price,
                    'stock' => $v->stock,
                    'sku' => $v->sku,
                ])->toArray(),
            ]);

        $response->assertRedirect(route('seller.products.edit', $product->id));
        $response->assertSessionHasErrors('variation_types');
        $this->assertStringContainsString(
            'maximum of 2 variation types',
            session('errors')->first('variation_types')
        );

        // Existing 6 rows are unchanged
        $this->assertCount(6, $product->fresh()->variants);
    }

    /**
     * SEL-VAR-003: Options per type at the limit (20 accepted; 21st blocked)
     */
    public function test_sel_var_003_options_per_type_at_the_limit(): void
    {
        $twentyColors = [];
        $twentyVariants = [];
        for ($i = 1; $i <= 20; $i++) {
            $colorName = "Color{$i}";
            $twentyColors[] = $colorName;
            $twentyVariants[] = [
                'color' => $colorName,
                'size' => 'S',
                'price' => 100.00,
                'stock' => 5,
                'sku' => "LIM-20-{$i}",
            ];
        }

        // 1. 20 options are accepted
        $res20 = $this->actingAs($this->seller1)->post(route('seller.products.store'), $this->validProductPayload([
            'name' => 'Twenty Colors Bag',
            'price' => 100.00,
            'stock' => 100,
            'variation_types' => [
                ['name' => 'Color', 'options' => $twentyColors],
                ['name' => 'Size', 'options' => ['S']],
            ],
            'variants' => $twentyVariants,
        ]));

        $res20->assertRedirect(route('seller.products'));
        $res20->assertSessionHasNoErrors();
        $this->assertEquals(20, Product::where('name', 'Twenty Colors Bag')->firstOrFail()->variants()->count());

        // 2. Attempt a 21st option -> blocked with limit message
        $twentyOneColors = [...$twentyColors, 'Color21'];
        $twentyOneVariants = [
            ...$twentyVariants,
            [
                'color' => 'Color21',
                'size' => 'S',
                'price' => 100.00,
                'stock' => 5,
                'sku' => 'LIM-21-21',
            ],
        ];

        $res21 = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Twenty One Colors Bag',
                'price' => 100.00,
                'stock' => 105,
                'variation_types' => [
                    ['name' => 'Color', 'options' => $twentyOneColors],
                    ['name' => 'Size', 'options' => ['S']],
                ],
                'variants' => $twentyOneVariants,
            ]));

        $res21->assertRedirect(route('seller.products.create'));
        $res21->assertSessionHasErrors('variation_types');
        $this->assertDatabaseMissing('products', ['name' => 'Twenty One Colors Bag']);
    }

    /**
     * SEL-VAR-004: Duplicate or empty option names -> Blocked with messages; no product created
     */
    public function test_sel_var_004_duplicate_or_empty_option_names_blocked(): void
    {
        // 1. Duplicate option names (case-insensitive: 'Red' and 'red')
        $dupResponse = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Dup Color Product',
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Red', 'red']],
                ],
                'variants' => [
                    ['color' => 'Red', 'size' => null, 'price' => 100.00, 'stock' => 5, 'sku' => 'DUP-1'],
                    ['color' => 'red', 'size' => null, 'price' => 100.00, 'stock' => 5, 'sku' => 'DUP-2'],
                ],
            ]));

        $dupResponse->assertRedirect(route('seller.products.create'));
        $dupResponse->assertSessionHasErrors('variation_types');
        $this->assertDatabaseMissing('products', ['name' => 'Dup Color Product']);

        // 2. Empty option name ('')
        $emptyResponse = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Empty Option Product',
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Red', '']],
                ],
                'variants' => [
                    ['color' => 'Red', 'size' => null, 'price' => 100.00, 'stock' => 5, 'sku' => 'EMP-1'],
                ],
            ]));

        $emptyResponse->assertRedirect(route('seller.products.create'));
        $emptyResponse->assertSessionHasErrors('variation_types');
        $this->assertDatabaseMissing('products', ['name' => 'Empty Option Product']);
    }

    /**
     * SEL-VAR-005: Missing or invalid price or stock on one variant
     */
    public function test_sel_var_005_missing_or_invalid_price_or_stock_on_one_variant_blocked(): void
    {
        $baseVariants = [
            ['color' => 'Red', 'size' => 'S', 'price' => 99.00, 'stock' => 5, 'sku' => 'BCP-RED-S'],
            ['color' => 'Red', 'size' => 'M', 'price' => 129.00, 'stock' => 5, 'sku' => 'BCP-RED-M'],
            ['color' => 'Red', 'size' => 'L', 'price' => 149.00, 'stock' => 5, 'sku' => 'BCP-RED-L'],
            ['color' => 'Blue', 'size' => 'S', 'price' => 99.00, 'stock' => 5, 'sku' => 'BCP-BLUE-S'],
            ['color' => 'Blue', 'size' => 'M', 'price' => 129.00, 'stock' => 5, 'sku' => 'BCP-BLUE-M'],
            ['color' => 'Blue', 'size' => 'L', 'price' => 149.00, 'stock' => 5, 'sku' => 'BCP-BLUE-L'],
        ];

        // 1. Blue-L (index 5) price blank
        $blankPriceVariants = $baseVariants;
        $blankPriceVariants[5]['price'] = '';

        $res1 = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Invalid Variant Product 1',
                'variants' => $blankPriceVariants,
            ]));

        $res1->assertRedirect(route('seller.products.create'));
        $res1->assertSessionHasErrors('variants.5.price');

        // 2. Blue-L (index 5) price ₱0.00
        $zeroPriceVariants = $baseVariants;
        $zeroPriceVariants[5]['price'] = 0.00;

        $res2 = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Invalid Variant Product 2',
                'variants' => $zeroPriceVariants,
            ]));

        $res2->assertRedirect(route('seller.products.create'));
        $res2->assertSessionHasErrors('variants.5.price');

        // 3. Blue-L (index 5) stock -1
        $negativeStockVariants = $baseVariants;
        $negativeStockVariants[5]['stock'] = -1;

        $res3 = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Invalid Variant Product 3',
                'variants' => $negativeStockVariants,
            ]));

        $res3->assertRedirect(route('seller.products.create'));
        $res3->assertSessionHasErrors('variants.5.stock');
    }

    /**
     * SEL-VAR-006: Duplicate SKU (same SKU twice in one product or SKU already used by another product)
     */
    public function test_sel_var_006_duplicate_sku_blocked(): void
    {
        $this->createP01Product(); // Creates SKU 'MCH-TOTE-NAT-S'

        // 1. Same SKU twice in one product ('BCP-RED-S' twice)
        $res1 = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Dup SKU Same Product',
                'variants' => [
                    ['color' => 'Red', 'size' => 'S', 'price' => 99.00, 'stock' => 5, 'sku' => 'BCP-RED-S'],
                    ['color' => 'Red', 'size' => 'M', 'price' => 129.00, 'stock' => 5, 'sku' => 'BCP-RED-S'],
                ],
            ]));

        $res1->assertRedirect(route('seller.products.create'));
        $res1->assertSessionHasErrors('variants.1.sku');

        // 2. SKU already used by another product ('MCH-TOTE-NAT-S')
        $res2 = $this->actingAs($this->seller1)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Dup SKU Existing Product',
                'variants' => [
                    ['color' => 'Red', 'size' => 'S', 'price' => 99.00, 'stock' => 5, 'sku' => 'MCH-TOTE-NAT-S'],
                ],
            ]));

        $res2->assertRedirect(route('seller.products.create'));
        $res2->assertSessionHasErrors('variants.0.sku');
    }

    /**
     * SEL-VAR-007: Price range shown on product page and search
     */
    public function test_sel_var_007_price_range_shown_on_product_page_and_search(): void
    {
        $product = $this->createP01Product();

        // 1. Search 'Abaca Tote' as Buyer 1 -> Search card shows ₱199 – ₱299
        $this->actingAs($this->buyer1)
            ->get(route('buyer.products', ['search' => 'Abaca Tote']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Products')
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.price_range', '₱199 – ₱299')
                ->where('products.data.0.min_price', '199.00')
                ->where('products.data.0.max_price', '299.00')
            );

        // 2. Open P-01 -> Product page shows ₱199 – ₱299 and Natural-M has price ₱249.00
        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $product->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.id', $product->id)
                ->where('product.price_range', '₱199 – ₱299')
                ->where('product.variants', function ($variants) {
                    $naturalM = collect($variants)->first(
                        fn ($v) => $v['color'] === 'Natural' && $v['size'] === 'M'
                    );
                    return $naturalM !== null && (float) $naturalM['price'] === 249.00;
                })
            );
    }

    /**
     * SEL-VAR-008: Variant image switching
     */
    public function test_sel_var_008_variant_image_switching(): void
    {
        $product = $this->createP01Product();

        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $product->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.variants', function ($variants) {
                    $natural = collect($variants)->firstWhere('color', 'Natural');
                    $black = collect($variants)->firstWhere('color', 'Black');

                    return $natural['image_path'] === 'products/variants/natural.png'
                        && $black['image_path'] === 'products/variants/black.png'
                        && $natural['image_path'] !== $black['image_path'];
                })
            );
    }

    /**
     * SEL-VAR-009: Variant with 0 stock
     */
    public function test_sel_var_009_variant_with_zero_stock(): void
    {
        $product = $this->createP01Product();
        $blackM = $product->variants()->where('color', 'Black')->where('size', 'M')->firstOrFail();
        $naturalM = $product->variants()->where('color', 'Natural')->where('size', 'M')->firstOrFail();

        $blackM->update(['stock' => 0]);

        // 1. Open P-01 -> Black-M has stock 0 (disabled in UI), Natural-M has stock 5 (selectable)
        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $product->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.variants', function ($variants) use ($blackM, $naturalM) {
                    $bm = collect($variants)->firstWhere('id', $blackM->id);
                    $nm = collect($variants)->firstWhere('id', $naturalM->id);
                    return (int) $bm['stock'] === 0 && (int) $nm['stock'] === 5;
                })
            );

        // 2. Attempting to add Black-M (0 stock) to cart is blocked
        $this->actingAs($this->buyer1)
            ->from(route('buyer.product', $product->id))
            ->post(route('buyer.cart.store', $product->id), [
                'product_variant_id' => $blackM->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->buyer1->id,
            'product_variant_id' => $blackM->id,
        ]);

        // 3. Natural-M remains selectable and can be added to cart
        $this->actingAs($this->buyer1)
            ->post(route('buyer.cart.store', $product->id), [
                'product_variant_id' => $naturalM->id,
                'quantity' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->buyer1->id,
            'product_variant_id' => $naturalM->id,
            'quantity' => 1,
        ]);
    }

    /**
     * SEL-VAR-010: Change options after orders exist
     */
    public function test_sel_var_010_change_options_after_orders_exist(): void
    {
        $product = $this->createP01Product();
        $naturalM = $product->variants()->where('color', 'Natural')->where('size', 'M')->firstOrFail();

        // Completed order exists for P-01 Natural-M at ₱249
        $order = Order::create([
            'order_number' => 'ORD-VAR-010',
            'user_id' => $this->buyer1->id,
            'shipping_address' => '123 Rizal St, Legazpi City',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_method' => 'standard',
            'subtotal' => 249.00,
            'shipping_fee' => 50.00,
            'discount' => 0.00,
            'total' => 299.00,
            'status' => 'completed',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $naturalM->id,
            'product_name' => $product->name,
            'variant_label' => 'Natural / M',
            'price' => 249.00,
            'quantity' => 1,
            'status' => 'completed',
        ]);

        // Seller 1 edits P-01: renames Natural to Beige, changes Beige-M price to ₱259, adds Brown
        $updatedVariants = [
            ['id' => $naturalM->id, 'color' => 'Beige', 'size' => 'M', 'price' => 259.00, 'stock' => 5, 'sku' => 'MCH-TOTE-BGE-M'],
            ['color' => 'Brown', 'size' => 'M', 'price' => 269.00, 'stock' => 5, 'sku' => 'MCH-TOTE-BRN-M'],
        ];

        $this->actingAs($this->seller1)
            ->from(route('seller.products'))
            ->patch(route('seller.products.update', $product->id), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'price' => 259.00,
                'stock' => 10,
                'weight' => $product->weight,
                'status' => 'approved',
                'variation_types' => [
                    ['name' => 'Color', 'options' => ['Beige', 'Brown']],
                    ['name' => 'Size', 'options' => ['M']],
                ],
                'variants' => $updatedVariants,
            ])
            ->assertRedirect(route('seller.products'))
            ->assertSessionHasNoErrors();

        // Old order still shows Natural-M at ₱249.00 in DB and for both Seller 1 and Buyer 1
        $orderItem->refresh();
        $this->assertEquals('Natural / M', $orderItem->variant_label);
        $this->assertEquals(249.00, (float) $orderItem->price);

        // View old order as Buyer 1
        $this->actingAs($this->buyer1)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Orders')
                ->where('orders.data.0.items.0.variant_label', 'Natural / M')
                ->where('orders.data.0.items.0.price', '249.00')
            );

        // View old order as Seller 1
        $this->actingAs($this->seller1)
            ->get(route('seller.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seller/Orders')
                ->where('orders.data.0.variant_label', 'Natural / M')
                ->where('orders.data.0.price', '249.00')
            );

        // Product page shows Beige and Brown with new price
        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $product->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.variants', function ($variants) {
                    $colors = collect($variants)->pluck('color')->unique()->values()->all();
                    $beigeM = collect($variants)->firstWhere('color', 'Beige');
                    return $colors === ['Beige', 'Brown'] && (float) $beigeM['price'] === 259.00;
                })
            );
    }

    /**
     * SEL-VAR-011: Remove a variant that is in a buyer's cart
     */
    public function test_sel_var_011_remove_variant_that_is_in_buyers_cart(): void
    {
        $product = $this->createP01Product();
        $blackL = $product->variants()->where('color', 'Black')->where('size', 'L')->firstOrFail();
        $naturalS = $product->variants()->where('color', 'Natural')->where('size', 'S')->firstOrFail();

        // Buyer 1 has P-01 Black-L and Natural-S in cart
        $removedCartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $product->id,
            'product_variant_id' => $blackL->id,
            'quantity' => 1,
        ]);

        $validCartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $product->id,
            'product_variant_id' => $naturalS->id,
            'quantity' => 1,
        ]);

        // Seller 1 deletes Black-L row and saves
        $remainingVariants = $product->variants
            ->reject(fn ($v) => $v->id === $blackL->id)
            ->map(fn ($v) => [
                'id' => $v->id,
                'color' => $v->color,
                'size' => $v->size,
                'price' => $v->price,
                'stock' => $v->stock,
                'sku' => $v->sku,
            ])
            ->values()
            ->all();

        $this->actingAs($this->seller1)
            ->from(route('seller.products'))
            ->patch(route('seller.products.update', $product->id), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => 25,
                'weight' => $product->weight,
                'status' => 'approved',
                'variants' => $remainingVariants,
            ])
            ->assertRedirect(route('seller.products'))
            ->assertSessionHasNoErrors();

        // Open Cart as Buyer 1 -> Black-L cart line still exists and shows 'Variation unavailable'
        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->has('items', 2)
                ->where('items', function ($items) use ($removedCartItem, $validCartItem) {
                    $unavailableLine = collect($items)->firstWhere('id', $removedCartItem->id);
                    $validLine = collect($items)->firstWhere('id', $validCartItem->id);

                    return $unavailableLine !== null
                        && $unavailableLine['is_unavailable'] === true
                        && $unavailableLine['unavailable_reason'] === 'Variation unavailable'
                        && $validLine !== null
                        && $validLine['is_unavailable'] === false;
                })
            );

        // Attempting to checkout the unavailable cart line is blocked
        $this->actingAs($this->buyer1)
            ->from(route('buyer.cart'))
            ->post(route('buyer.orders.store'), [
                'selected_items' => [$removedCartItem->id],
                'shipping_address' => '123 Rizal St, Legazpi City, Albay',
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
            ])
            ->assertSessionHasErrors('selected_items');

        // Other cart lines still check out successfully
        $this->actingAs($this->buyer1)
            ->post(route('buyer.orders.store'), [
                'selected_items' => [$validCartItem->id],
                'shipping_address' => '123 Rizal St, Legazpi City, Albay',
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_variant_id' => $naturalS->id,
            'price' => 199.00,
        ]);
    }

    /**
     * SEL-VAR-012: Remove a variant with unfinished orders
     */
    public function test_sel_var_012_remove_variant_with_unfinished_orders_blocked(): void
    {
        $product = $this->createP01Product();
        $naturalS = $product->variants()->where('color', 'Natural')->where('size', 'S')->firstOrFail();

        // P-01 Natural-S is in a 'To Ship' (processing) order
        $order = Order::create([
            'order_number' => 'ORD-VAR-012',
            'user_id' => $this->buyer1->id,
            'shipping_address' => '123 Rizal St, Legazpi City',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'standard',
            'subtotal' => 199.00,
            'shipping_fee' => 50.00,
            'discount' => 0.00,
            'total' => 249.00,
            'status' => 'processing',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $naturalS->id,
            'product_name' => $product->name,
            'variant_label' => 'Natural / S',
            'price' => 199.00,
            'quantity' => 1,
            'status' => 'processing',
        ]);

        // Seller tries to delete the Natural-S row and save
        $variantsWithoutNaturalS = $product->variants
            ->reject(fn ($v) => $v->id === $naturalS->id)
            ->map(fn ($v) => [
                'id' => $v->id,
                'color' => $v->color,
                'size' => $v->size,
                'price' => $v->price,
                'stock' => $v->stock,
                'sku' => $v->sku,
            ])
            ->values()
            ->all();

        $response = $this->actingAs($this->seller1)
            ->from(route('seller.products.edit', $product->id))
            ->patch(route('seller.products.update', $product->id), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => 25,
                'weight' => $product->weight,
                'status' => 'approved',
                'variants' => $variantsWithoutNaturalS,
            ]);

        $response->assertRedirect(route('seller.products.edit', $product->id));
        $response->assertSessionHasErrors('variants');
        $this->assertStringContainsString(
            'unfinished orders',
            session('errors')->first('variants')
        );

        // Natural-S stays on the product
        $this->assertDatabaseHas('product_variants', [
            'id' => $naturalS->id,
            'product_id' => $product->id,
            'color' => 'Natural',
            'size' => 'S',
        ]);

        // And the order can still be packed and readied for pickup / shipped
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.pack', $orderItem->id))
            ->assertSessionHasNoErrors();

        $this->assertEquals('packed', $orderItem->fresh()->status);

        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.ready-for-pickup', $orderItem->id))
            ->assertSessionHasNoErrors();

        $this->assertEquals('ready_for_pickup', $orderItem->fresh()->status);
    }
}

