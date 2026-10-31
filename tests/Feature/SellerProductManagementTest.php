<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller1;
    protected User $seller2;
    protected User $buyer;
    protected Category $category;
    protected StoreCategory $storeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller1 = User::factory()->create([
            'name' => 'Seller Maria',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Maria Handicrafts',
            'contact_no' => '09171112233',
        ]);

        $this->seller2 = User::factory()->create([
            'name' => 'Seller Juan',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Juan Tech',
            'contact_no' => '09182223344',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Buyer Elena',
            'usertype' => 'buyer',
            'status' => 'approved',
            'contact_no' => '09193334455',
        ]);

        $this->category = Category::create([
            'name' => 'Home & Living',
            'slug' => 'home-living',
            'is_active' => true,
        ]);

        $this->storeCategory = StoreCategory::create([
            'seller_id' => $this->seller1->id,
            'name' => 'Kitchen & Dining',
            'slug' => 'kitchen-dining',
            'is_visible' => true,
        ]);
    }

    private function validProductPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Buri Placemat Set',
            'description' => 'Set of 6 placemats, handwoven buri palm for daily dining.',
            'category_id' => $this->category->id,
            'store_category_ids' => [$this->storeCategory->id],
            'brand' => 'No brand',
            'condition' => 'New',
            'price' => '299.00',
            'stock' => 30,
            'weight' => 0.6,
            'length' => 35,
            'width' => 25,
            'height' => 3,
            'status' => 'pending',
            'images' => [
                UploadedFile::fake()->image('product_front.jpg', 600, 600),
                UploadedFile::fake()->image('product_side.png', 600, 600),
            ],
        ], $overrides);
    }

    /**
     * SEL-PROD-001: Add and publish a product with all valid data
     */
    public function test_sel_prod_001_add_and_publish_product_with_valid_data(): void
    {
        $payload = $this->validProductPayload();

        $response = $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $payload);

        $response->assertRedirect(route('seller.products'));

        $this->assertDatabaseHas('products', [
            'seller_id' => $this->seller1->id,
            'name' => 'Buri Placemat Set',
            'price' => 299.00,
            'stock' => 30,
            'weight' => 0.6,
        ]);

        $product = Product::where('name', 'Buri Placemat Set')->firstOrFail();
        $this->assertCount(2, $product->images);
        $this->assertEquals(0, $product->images[0]->sort_order);
        $this->assertEquals(1, $product->images[1]->sort_order);

        // Mark approved (active) and verify buyer visibility
        $product->update(['status' => 'approved']);

        // Buyer searches for name
        $searchResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.products', ['search' => 'Buri Placemat']));
        $searchResponse->assertOk();
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
        );

        // Buyer opens platform category
        $categoryResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.products', ['category' => 'home-living']));
        $categoryResponse->assertOk();
        $categoryResponse->assertInertia(fn (Assert $page) => $page
            ->where('products.data', fn ($items) => collect($items)->pluck('id')->contains($product->id))
        );

        // Buyer opens store page with store category
        $storeResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', [
                'seller' => $this->seller1->id,
                'category' => 'kitchen-dining',
            ]));
        $storeResponse->assertOk();
        $storeResponse->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
        );
    }

    /**
     * SEL-PROD-002: Each required field left blank
     */
    public function test_sel_prod_002_each_required_field_left_blank(): void
    {
        $requiredFields = ['name', 'description', 'category_id', 'price', 'stock', 'weight', 'images'];

        foreach ($requiredFields as $field) {
            $payload = $this->validProductPayload([$field => null]);
            if ($field === 'images') {
                unset($payload['images']);
            }

            $response = $this->actingAs($this->seller1)
                ->post(route('seller.products.store'), $payload);

            $response->assertSessionHasErrors([$field]);
        }
    }

    /**
     * SEL-PROD-003: Product name and description length
     */
    public function test_sel_prod_003_product_name_and_description_length(): void
    {
        // Name 4 chars (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['name' => 'Tote']))
            ->assertSessionHasErrors(['name']);

        // Name 5 chars (accept)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['name' => 'Totes']))
            ->assertSessionHasNoErrors();

        // Name 120 chars (accept)
        $name120 = str_repeat('A', 120);
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['name' => $name120]))
            ->assertSessionHasNoErrors();

        // Name 121 chars (reject)
        $name121 = str_repeat('A', 121);
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['name' => $name121]))
            ->assertSessionHasErrors(['name']);

        // Description 19 chars (reject)
        $desc19 = str_repeat('D', 19);
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['description' => $desc19]))
            ->assertSessionHasErrors(['description']);

        // Description 20 chars (accept)
        $desc20 = str_repeat('D', 20);
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['description' => $desc20]))
            ->assertSessionHasNoErrors();

        // Description 3,000 chars (accept)
        $desc3000 = str_repeat('D', 3000);
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['description' => $desc3000]))
            ->assertSessionHasNoErrors();

        // Description 3,001 chars (reject)
        $desc3001 = str_repeat('D', 3001);
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['description' => $desc3001]))
            ->assertSessionHasErrors(['description']);
    }

    /**
     * SEL-PROD-004: Price validation
     */
    public function test_sel_prod_004_price_validation(): void
    {
        $invalidPrices = ['0', '-50', '0.99', '199.999', '1000000.01', 'abc', ''];
        foreach ($invalidPrices as $price) {
            $this->actingAs($this->seller1)
                ->post(route('seller.products.store'), $this->validProductPayload(['price' => $price]))
                ->assertSessionHasErrors(['price']);
        }

        $validPrices = ['1.00', '1.01', '999999.99', '1000000.00'];
        foreach ($validPrices as $price) {
            $this->actingAs($this->seller1)
                ->post(route('seller.products.store'), $this->validProductPayload([
                    'name' => 'Price Test ' . $price,
                    'price' => $price,
                ]))
                ->assertSessionHasNoErrors();
        }
    }

    /**
     * SEL-PROD-005: Stock validation
     */
    public function test_sel_prod_005_stock_validation(): void
    {
        $invalidStocks = [-1, 100000, 2.5];
        foreach ($invalidStocks as $stock) {
            $this->actingAs($this->seller1)
                ->post(route('seller.products.store'), $this->validProductPayload(['stock' => $stock]))
                ->assertSessionHasErrors(['stock']);
        }

        $validStocks = [0, 1, 99999];
        foreach ($validStocks as $stock) {
            $this->actingAs($this->seller1)
                ->post(route('seller.products.store'), $this->validProductPayload([
                    'name' => 'Stock Test ' . $stock,
                    'stock' => $stock,
                ]))
                ->assertSessionHasNoErrors();
        }
    }

    /**
     * SEL-PROD-006: Image count at the limits
     */
    public function test_sel_prod_006_image_count_at_the_limits(): void
    {
        // 0 images (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['images' => []]))
            ->assertSessionHasErrors(['images']);

        // 1 image (accept)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Single Image Product',
                'images' => [UploadedFile::fake()->image('one.jpg')],
            ]))
            ->assertSessionHasNoErrors();

        // 9 images (accept)
        $nineImages = [];
        for ($i = 1; $i <= 9; $i++) {
            $nineImages[] = UploadedFile::fake()->image("img_{$i}.jpg");
        }
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Nine Images Product',
                'images' => $nineImages,
            ]))
            ->assertSessionHasNoErrors();

        // 10 images (reject)
        $tenImages = [];
        for ($i = 1; $i <= 10; $i++) {
            $tenImages[] = UploadedFile::fake()->image("img_{$i}.jpg");
        }
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Ten Images Product',
                'images' => $tenImages,
            ]))
            ->assertSessionHasErrors(['images']);
    }

    /**
     * SEL-PROD-007: Image file type and size
     */
    public function test_sel_prod_007_image_file_type_and_size(): void
    {
        // 5.0 MB jpg (accept)
        $file5MB = UploadedFile::fake()->create('size_5.0MB.jpg', 5120, 'image/jpeg');
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Five MB Product',
                'images' => [$file5MB],
            ]))
            ->assertSessionHasNoErrors();

        // 5.1 MB jpg (reject)
        $file5_1MB = UploadedFile::fake()->create('size_5.1MB.jpg', 5222, 'image/jpeg');
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['images' => [$file5_1MB]]))
            ->assertSessionHasErrors();

        // docx (reject)
        $docxFile = UploadedFile::fake()->create('price_list.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['images' => [$docxFile]]))
            ->assertSessionHasErrors();
    }

    /**
     * SEL-PROD-008: Weight and dimensions validation
     */
    public function test_sel_prod_008_weight_and_dimensions_validation(): void
    {
        // Weight 0 (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['weight' => 0]))
            ->assertSessionHasErrors(['weight']);

        // Weight -1 (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload(['weight' => -1]))
            ->assertSessionHasErrors(['weight']);

        // Weight 0.01 kg (accept)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'name' => 'Lightweight Product',
                'weight' => 0.01,
            ]))
            ->assertSessionHasNoErrors();

        // Dimensions 0 x 0 x 0 (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'length' => 0,
                'width' => 0,
                'height' => 0,
            ]))
            ->assertSessionHasErrors(['dimensions']);

        // Dimensions abc (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $this->validProductPayload([
                'length' => 'abc',
                'width' => 'abc',
                'height' => 'abc',
            ]))
            ->assertSessionHasErrors(['dimensions']);
    }

    /**
     * SEL-PROD-009: Save as draft
     */
    public function test_sel_prod_009_save_as_draft(): void
    {
        $response = $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), [
                'name' => 'Draft Woven Fan',
                'status' => 'draft',
            ]);

        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Draft Woven Fan')->firstOrFail();
        $this->assertEquals('draft', $product->status);

        // Does not appear to buyer search
        $searchResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.products', ['search' => 'Draft Woven Fan']));
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
        );

        // Direct URL returns 404
        $directResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.product', $product));
        $directResponse->assertNotFound();
    }

    /**
     * SEL-PROD-010: Publish a draft with missing data
     */
    public function test_sel_prod_010_publish_draft_with_missing_data(): void
    {
        $draft = Product::create([
            'seller_id' => $this->seller1->id,
            'name' => 'Draft Woven Fan',
            'slug' => 'draft-woven-fan',
            'status' => 'draft',
        ]);

        // Attempt to publish without filling required fields
        $response = $this->actingAs($this->seller1)
            ->patch(route('seller.products.update', $draft->id), [
                'name' => 'Draft Woven Fan',
                'status' => 'pending',
                // Explicit empty weight must fail; an omitted field has an existing 0.5 kg API default.
                'weight' => null,
            ]);

        $response->assertSessionHasErrors(['description', 'category_id', 'price', 'stock', 'weight', 'images']);
        $this->assertEquals('draft', $draft->fresh()->status);
    }

    /**
     * SEL-PROD-011: Deactivate and reactivate a product
     */
    public function test_sel_prod_011_deactivate_and_reactivate_product(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Rattan Fruit Basket',
            'slug' => 'rattan-fruit-basket',
            'description' => 'A beautifully handwoven rattan basket.',
            'price' => 450.00,
            'stock' => 10,
            'weight' => 0.5,
            'status' => 'approved',
        ]);

        // Buyer adds to cart
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Seller deactivates product
        $this->actingAs($this->seller1)
            ->patch(route('seller.products.status', $product->id), [
                'status' => 'inactive',
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals('inactive', $product->fresh()->status);

        // Buyer search: missing
        $searchResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.products', ['search' => 'Rattan Fruit Basket']));
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
        );

        // Direct URL: 404
        $this->actingAs($this->buyer)
            ->get(route('buyer.product', $product))
            ->assertNotFound();

        // Cart line cannot be updated/checked out while inactive
        $cartUpdateResponse = $this->actingAs($this->buyer)
            ->patch(route('buyer.cart.update', $cartItem->id), [
                'quantity' => 2,
            ]);
        $cartUpdateResponse->assertStatus(422);

        // Reactivate product
        $this->actingAs($this->seller1)
            ->patch(route('seller.products.status', $product->id), [
                'status' => 'approved',
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals('approved', $product->fresh()->status);

        // Now buyer can update and checkout
        $this->actingAs($this->buyer)
            ->get(route('buyer.product', $product))
            ->assertOk();
    }

    /**
     * SEL-PROD-012: Duplicate a product
     */
    public function test_sel_prod_012_duplicate_product(): void
    {
        $original = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Original Basket',
            'slug' => 'original-basket',
            'description' => 'Original high quality basket description.',
            'price' => 350.00,
            'stock' => 12,
            'weight' => 0.8,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->seller1)
            ->post(route('seller.products.duplicate', $original->id));

        $response->assertRedirect(route('seller.products'));

        $this->assertDatabaseCount('products', 2);

        $copy = Product::where('id', '!=', $original->id)->firstOrFail();
        $this->assertEquals($original->name, $copy->name);
        $this->assertEquals('draft', $copy->status);
        $this->assertEquals(350.00, $copy->price);
        $this->assertNotEquals($original->slug, $copy->slug);
    }

    /**
     * SEL-PROD-013: Edit price of a product in a buyer's cart
     */
    public function test_sel_prod_013_edit_price_of_product_in_buyers_cart(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Rattan Fruit Basket',
            'slug' => 'rattan-fruit-basket',
            'description' => 'A beautifully handwoven rattan basket.',
            'price' => 450.00,
            'stock' => 10,
            'weight' => 0.5,
            'image_path' => 'products/sample.jpg',
            'status' => 'approved',
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Seller changes price to 420.00
        $this->actingAs($this->seller1)
            ->patch(route('seller.products.update', $product->id), [
                'name' => $product->name,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'price' => '420.00',
                'stock' => $product->stock,
                'weight' => $product->weight,
                'status' => 'approved',
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(420.00, $product->fresh()->price);

        // Buyer cart shows recalculated price 420.00
        $cartResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.cart'));
        $cartResponse->assertOk();
        $cartResponse->assertInertia(fn (Assert $page) => $page
            ->where('items.0.product.price', '420.00')
        );

        // Buyer checkout shows 420.00
        $checkoutResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.checkout', [
                'selected_items' => [$cartItem->id],
            ]));
        $checkoutResponse->assertOk();
        $checkoutResponse->assertInertia(fn (Assert $page) => $page
            ->where('items.0.product.price', '420.00')
        );
    }

    /**
     * SEL-PROD-014: Delete a product with unfinished orders
     */
    public function test_sel_prod_014_delete_product_with_unfinished_orders(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Unfinished Order Product',
            'slug' => 'unfinished-order-product',
            'description' => 'Product description with more than 20 chars.',
            'price' => 500.00,
            'stock' => 5,
            'weight' => 1.0,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-1001',
            'status' => 'to_ship',
            'subtotal' => 500.00,
            'total' => 500.00,
            'shipping_address' => 'Sample Address',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 500.00,
            'quantity' => 1,
            'subtotal' => 500.00,
        ]);

        // Attempt delete
        $response = $this->actingAs($this->seller1)
            ->delete(route('seller.products.destroy', $product->id));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'approved']);
    }

    /**
     * SEL-PROD-015: Delete a product with only finished orders
     */
    public function test_sel_prod_015_delete_product_with_only_finished_orders(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Finished Order Product',
            'slug' => 'finished-order-product',
            'description' => 'Product description with more than 20 chars.',
            'price' => 300.00,
            'stock' => 5,
            'weight' => 1.0,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-1002',
            'status' => 'completed',
            'subtotal' => 300.00,
            'total' => 300.00,
            'shipping_address' => 'Sample Address',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 300.00,
            'quantity' => 1,
            'subtotal' => 300.00,
        ]);

        $response = $this->actingAs($this->seller1)
            ->delete(route('seller.products.destroy', $product->id));

        $response->assertSessionHasNoErrors();

        // Product is archived and removed from active storefront/search
        $this->assertEquals('archived', $product->fresh()->status);

        $searchResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.products', ['search' => 'Finished Order Product']));
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
        );

        // Buyer order details view still shows name and price paid
        $orderResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.orders.show', $order->id));
        $orderResponse->assertOk();
        $orderResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.product_name', 'Finished Order Product')
            ->where('order.items.0.price', '300.00')
        );
    }

    /**
     * SEL-PROD-016: Bulk deactivate and bulk delete with a mixed selection
     */
    public function test_sel_prod_016_bulk_deactivate_and_bulk_delete_with_mixed_selection(): void
    {
        $p1 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Product 1 (Has To Ship Order)',
            'slug' => 'p1-has-order',
            'description' => 'Product description with more than 20 chars.',
            'price' => 100.00,
            'stock' => 5,
            'weight' => 1.0,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-1003',
            'status' => 'to_ship',
            'subtotal' => 100.00,
            'total' => 100.00,
            'shipping_address' => 'Sample Address',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'price' => 100.00,
            'quantity' => 1,
            'subtotal' => 100.00,
        ]);

        $p2 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Product 2 (No Orders)',
            'slug' => 'p2-no-orders',
            'description' => 'Product description with more than 20 chars.',
            'price' => 200.00,
            'stock' => 10,
            'weight' => 1.0,
            'status' => 'approved',
        ]);

        $p3 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Product 3 (No Orders)',
            'slug' => 'p3-no-orders',
            'description' => 'Product description with more than 20 chars.',
            'price' => 300.00,
            'stock' => 15,
            'weight' => 1.0,
            'status' => 'approved',
        ]);

        // 1. Bulk deactivate all three
        $this->actingAs($this->seller1)
            ->post(route('seller.products.bulk-deactivate'), [
                'product_ids' => [$p1->id, $p2->id, $p3->id],
            ])
            ->assertRedirect(route('seller.products'));

        $this->assertEquals('inactive', $p1->fresh()->status);
        $this->assertEquals('inactive', $p2->fresh()->status);
        $this->assertEquals('inactive', $p3->fresh()->status);

        // 2. Bulk delete all three
        $response = $this->actingAs($this->seller1)
            ->post(route('seller.products.bulk-delete'), [
                'product_ids' => [$p1->id, $p2->id, $p3->id],
            ]);

        $response->assertRedirect(route('seller.products'));
        $response->assertSessionHas('status', fn ($msg) => str_contains($msg, 'skipped because it has unfinished orders'));

        // P1 remains present
        $this->assertDatabaseHas('products', ['id' => $p1->id]);
        // P2 and P3 deleted
        $this->assertDatabaseMissing('products', ['id' => $p2->id]);
        $this->assertDatabaseMissing('products', ['id' => $p3->id]);
    }

    /**
     * SEL-PROD-017: Double-click Publish
     */
    public function test_sel_prod_017_double_click_publish(): void
    {
        $payload = $this->validProductPayload(['name' => 'Double Click Test Tote']);

        // First click
        $res1 = $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $payload);
        $res1->assertRedirect(route('seller.products'));

        // Second click immediately
        $res2 = $this->actingAs($this->seller1)
            ->post(route('seller.products.store'), $payload);
        $res2->assertRedirect(route('seller.products'));

        // Exactly one product created
        $this->assertEquals(1, Product::where('name', 'Double Click Test Tote')->count());
    }

    /**
     * SEL-PROD-018: Edit another seller's product
     */
    public function test_sel_prod_018_edit_another_sellers_product(): void
    {
        $seller2Product = Product::create([
            'seller_id' => $this->seller2->id,
            'category_id' => $this->category->id,
            'name' => 'Juan Gaming Mouse',
            'slug' => 'juan-gaming-mouse',
            'description' => 'A gaming mouse from seller 2.',
            'price' => 899.00,
            'stock' => 15,
            'weight' => 0.3,
            'status' => 'approved',
        ]);

        // Seller 1 tries to open edit page of Seller 2's product
        $this->actingAs($this->seller1)
            ->get(route('seller.products.edit', $seller2Product->id))
            ->assertStatus(403);

        // Seller 1 tries to update Seller 2's product
        $this->actingAs($this->seller1)
            ->patch(route('seller.products.update', $seller2Product->id), [
                'name' => 'Hacked Mouse Name',
                'description' => 'Trying to maliciously edit this product.',
                'price' => 100.00,
            ])
            ->assertStatus(403);

        $this->assertEquals('Juan Gaming Mouse', $seller2Product->fresh()->name);
    }
}
