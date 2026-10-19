<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerStoreCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerA;
    protected User $sellerB;
    protected User $buyer;
    protected Category $platformCategory;
    protected Product $product1;
    protected Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sellerA = User::factory()->create([
            'name' => 'Seller Alice',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Alice Boutique',
            'contact_no' => '09171112233',
        ]);

        $this->sellerB = User::factory()->create([
            'name' => 'Seller Bob',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Bob Gadgets',
            'contact_no' => '09182223344',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Buyer Charlie',
            'usertype' => 'buyer',
            'status' => 'active',
            'contact_no' => '09193334455',
        ]);

        $this->platformCategory = Category::create([
            'name' => 'Apparel',
            'slug' => 'apparel',
            'is_active' => true,
        ]);

        $this->product1 = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Silk Blouse',
            'slug' => 'silk-blouse',
            'description' => 'A fine silk blouse.',
            'price' => 450.00,
            'stock' => 15,
            'status' => 'approved',
        ]);

        $this->product2 = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Denim Jeans',
            'slug' => 'denim-jeans',
            'description' => 'Comfortable denim jeans.',
            'price' => 750.00,
            'stock' => 20,
            'status' => 'approved',
        ]);
    }

    /**
     * SEL-SCAT-001: Create store category and assign product
     */
    public function test_sel_scat_001_create_store_category_and_assign_product(): void
    {
        // 1. Seller creates category "Tops"
        $response = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => 'Tops',
                'product_ids' => [$this->product1->id],
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('store_categories', [
            'seller_id' => $this->sellerA->id,
            'name' => 'Tops',
            'slug' => 'tops',
        ]);

        $category = StoreCategory::where('seller_id', $this->sellerA->id)
            ->where('name', 'Tops')
            ->firstOrFail();

        $this->assertTrue($category->products->contains($this->product1->id));
        $this->assertFalse($category->products->contains($this->product2->id));

        // 2. Buyer visits storefront filtering by the category
        $storeResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', [
                'seller' => $this->sellerA->id,
                'category' => 'tops',
            ]));

        $storeResponse->assertOk();
        $storeResponse->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/SellerStore')
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->product1->id)
        );
    }

    /**
     * SEL-SCAT-002: Category name length & emptiness validation
     */
    public function test_sel_scat_002_category_name_length_and_emptiness_validation(): void
    {
        // 1. Empty string
        $resEmpty = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => '',
            ]);
        $resEmpty->assertSessionHasErrors(['name' => 'Category name is required']);

        // 2. Spaces only
        $resSpaces = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => '     ',
            ]);
        $resSpaces->assertSessionHasErrors(['name' => 'Category name is required']);

        // 3. 1 character (accepted)
        $res1Char = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => 'A',
            ]);
        $res1Char->assertSessionHasNoErrors();
        $this->assertDatabaseHas('store_categories', [
            'seller_id' => $this->sellerA->id,
            'name' => 'A',
        ]);

        // 4. 50 characters (accepted)
        $fiftyChars = str_repeat('X', 50);
        $res50Chars = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => $fiftyChars,
            ]);
        $res50Chars->assertSessionHasNoErrors();
        $this->assertDatabaseHas('store_categories', [
            'seller_id' => $this->sellerA->id,
            'name' => $fiftyChars,
        ]);

        // 5. 51 characters (rejected)
        $fiftyOneChars = str_repeat('Y', 51);
        $res51Chars = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => $fiftyOneChars,
            ]);
        $res51Chars->assertSessionHasErrors(['name' => 'Category name cannot exceed 50 characters']);
    }

    /**
     * SEL-SCAT-003: Duplicate category name in same store
     */
    public function test_sel_scat_003_duplicate_category_name_in_same_store(): void
    {
        StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Tops',
            'slug' => 'tops',
        ]);

        // Exact match
        $resExact = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => 'Tops',
            ]);
        $resExact->assertSessionHasErrors(['name' => 'A category with this name already exists in your store']);

        // Case-insensitive & trimmed duplicate
        $resCase = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => '  tops  ',
            ]);
        $resCase->assertSessionHasErrors(['name' => 'A category with this name already exists in your store']);
    }

    /**
     * SEL-SCAT-004: Same category name in different store
     */
    public function test_sel_scat_004_same_category_name_in_different_store(): void
    {
        // Seller A creates "Tops"
        $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => 'Tops',
            ])
            ->assertSessionHasNoErrors();

        // Seller B creates "Tops" - should succeed because categories are isolated per store
        $this->actingAs($this->sellerB)
            ->post(route('seller.categories.store'), [
                'name' => 'Tops',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('store_categories', 2);
    }

    /**
     * SEL-SCAT-005: Rename to duplicate name blocked
     */
    public function test_sel_scat_005_rename_to_duplicate_name_blocked(): void
    {
        $cat1 = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Tops',
            'slug' => 'tops',
        ]);

        $cat2 = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Bottoms',
            'slug' => 'bottoms',
        ]);

        $response = $this->actingAs($this->sellerA)
            ->patch(route('seller.categories.update', $cat2->id), [
                'name' => 'tops',
            ]);

        $response->assertSessionHasErrors(['name' => 'A category with this name already exists in your store']);
        $this->assertEquals('Bottoms', $cat2->fresh()->name);
    }

    /**
     * SEL-SCAT-006: Rename and reorder categories
     */
    public function test_sel_scat_006_rename_and_reorder_categories(): void
    {
        $cat = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Old Name',
            'slug' => 'old-name',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->sellerA)
            ->patch(route('seller.categories.update', $cat->id), [
                'name' => 'Summer Tops',
                'sort_order' => 5,
            ]);

        $response->assertSessionHasNoErrors();

        $fresh = $cat->fresh();
        $this->assertEquals('Summer Tops', $fresh->name);
        $this->assertEquals(5, $fresh->sort_order);

        // Batch reorder endpoint
        $reorderResponse = $this->actingAs($this->sellerA)
            ->patch(route('seller.categories.reorder'), [
                'categories' => [
                    ['id' => $cat->id, 'sort_order' => 10],
                ],
            ]);

        $reorderResponse->assertSessionHasNoErrors();
        $this->assertEquals(10, $cat->fresh()->sort_order);
    }

    /**
     * SEL-SCAT-007: Hide category
     */
    public function test_sel_scat_007_hide_category(): void
    {
        $cat = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Seasonal',
            'slug' => 'seasonal',
            'is_visible' => true,
        ]);
        $cat->products()->attach($this->product1->id);

        // Seller hides the category
        $this->actingAs($this->sellerA)
            ->patch(route('seller.categories.update', $cat->id), [
                'is_visible' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($cat->fresh()->is_visible);

        // Store page categories does NOT expose the hidden category
        $storeResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', $this->sellerA->id));

        $storeResponse->assertOk();
        $storeResponse->assertInertia(fn (Assert $page) => $page
            ->where('categories', fn ($categories) => collect($categories)->doesntContain('slug', 'seasonal'))
        );

        // Direct URL to hidden category returns 404
        $directHiddenResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', [
                'seller' => $this->sellerA->id,
                'category' => 'seasonal',
            ]));

        $directHiddenResponse->assertNotFound();

        // Product inside the hidden category is still active and buyable on general store listing
        $generalResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', $this->sellerA->id));

        $generalResponse->assertInertia(fn (Assert $page) => $page
            ->where('products.data', fn ($items) => collect($items)->pluck('id')->contains($this->product1->id))
            ->etc()
        );
    }

    /**
     * SEL-SCAT-008: Show hidden category
     */
    public function test_sel_scat_008_show_hidden_category(): void
    {
        $cat = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Winter Clearance',
            'slug' => 'winter-clearance',
            'is_visible' => false,
        ]);
        $cat->products()->attach($this->product1->id);

        // Restore to visible
        $this->actingAs($this->sellerA)
            ->patch(route('seller.categories.update', $cat->id), [
                'is_visible' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($cat->fresh()->is_visible);

        // Reappears on storefront and filtering works
        $storeResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', [
                'seller' => $this->sellerA->id,
                'category' => 'winter-clearance',
            ]));

        $storeResponse->assertOk();
        $storeResponse->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->product1->id)
        );
    }

    /**
     * SEL-SCAT-009: Delete category with products
     */
    public function test_sel_scat_009_delete_category_with_products(): void
    {
        $cat = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'To Be Deleted',
            'slug' => 'to-be-deleted',
        ]);
        $cat->products()->attach($this->product1->id);

        $this->actingAs($this->sellerA)
            ->delete(route('seller.categories.destroy', $cat->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('store_categories', ['id' => $cat->id]);
        $this->assertDatabaseMissing('product_store_category', ['store_category_id' => $cat->id]);

        // Product itself is NOT deleted, status remains approved and stock intact
        $this->assertDatabaseHas('products', [
            'id' => $this->product1->id,
            'status' => 'approved',
        ]);
    }

    /**
     * SEL-SCAT-010: Delete parent category with subcategories
     */
    public function test_sel_scat_010_delete_parent_category_with_subcategories(): void
    {
        $parent = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Women',
            'slug' => 'women',
        ]);
        $sub = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'parent_id' => $parent->id,
            'name' => 'Dresses',
            'slug' => 'dresses',
        ]);

        $parent->products()->attach($this->product1->id);
        $sub->products()->attach($this->product2->id);

        $this->actingAs($this->sellerA)
            ->delete(route('seller.categories.destroy', $parent->id))
            ->assertSessionHasNoErrors();

        // Both parent and subcategory deleted
        $this->assertDatabaseMissing('store_categories', ['id' => $parent->id]);
        $this->assertDatabaseMissing('store_categories', ['id' => $sub->id]);

        // Products remain active and buyable
        $this->assertDatabaseHas('products', ['id' => $this->product1->id]);
        $this->assertDatabaseHas('products', ['id' => $this->product2->id]);
    }

    /**
     * SEL-SCAT-011: Subcategory nesting depth limit
     */
    public function test_sel_scat_011_subcategory_nesting_depth_limit(): void
    {
        // Level 1: Root
        $level1 = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Fashion',
            'slug' => 'fashion',
        ]);

        // Level 2: Subcategory under Level 1 (accepted)
        $resLevel2 = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => 'Shoes',
                'parent_id' => $level1->id,
            ]);
        $resLevel2->assertSessionHasNoErrors();

        $level2 = StoreCategory::where('seller_id', $this->sellerA->id)
            ->where('name', 'Shoes')
            ->firstOrFail();
        $this->assertEquals($level1->id, $level2->parent_id);

        // Level 3: Subcategory under Level 2 (rejected)
        $resLevel3 = $this->actingAs($this->sellerA)
            ->post(route('seller.categories.store'), [
                'name' => 'Sneakers',
                'parent_id' => $level2->id,
            ]);

        $resLevel3->assertSessionHasErrors([
            'parent_id' => 'Maximum category nesting depth of 2 levels reached',
        ]);
    }

    /**
     * SEL-SCAT-012: Product in several store categories
     */
    public function test_sel_scat_012_product_in_several_store_categories(): void
    {
        $cat1 = StoreCategory::create(['seller_id' => $this->sellerA->id, 'name' => 'Cat 1', 'slug' => 'cat-1']);
        $cat2 = StoreCategory::create(['seller_id' => $this->sellerA->id, 'name' => 'Cat 2', 'slug' => 'cat-2']);
        $cat3 = StoreCategory::create(['seller_id' => $this->sellerA->id, 'name' => 'Cat 3', 'slug' => 'cat-3']);
        $cat4 = StoreCategory::create(['seller_id' => $this->sellerA->id, 'name' => 'Cat 4', 'slug' => 'cat-4']);

        // Assign to 3 categories (accepted)
        $res3 = $this->actingAs($this->sellerA)
            ->post(route('seller.products.store-categories.assign', $this->product1->id), [
                'store_category_ids' => [$cat1->id, $cat2->id, $cat3->id],
            ]);
        $res3->assertSessionHasNoErrors();
        $this->assertCount(3, $this->product1->fresh()->storeCategories);

        // Assign to 4 categories (rejected with message)
        $res4 = $this->actingAs($this->sellerA)
            ->post(route('seller.products.store-categories.assign', $this->product1->id), [
                'store_category_ids' => [$cat1->id, $cat2->id, $cat3->id, $cat4->id],
            ]);
        $res4->assertSessionHasErrors([
            'store_category_ids' => 'A product can be assigned to a maximum of 3 store categories',
        ]);
    }

    /**
     * SEL-SCAT-013: Store category is separate from platform category
     */
    public function test_sel_scat_013_store_category_is_separate_from_platform_category(): void
    {
        $originalPlatformCategoryId = $this->product1->category_id;
        $this->assertNotNull($originalPlatformCategoryId);

        $storeCat = StoreCategory::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Promotional Highlights',
            'slug' => 'promotional-highlights',
        ]);

        // Assign product to store category
        $this->actingAs($this->sellerA)
            ->post(route('seller.products.store-categories.assign', $this->product1->id), [
                'store_category_ids' => [$storeCat->id],
            ])
            ->assertSessionHasNoErrors();

        $freshProduct = $this->product1->fresh();

        // Store category is attached
        $this->assertTrue($freshProduct->storeCategories->contains($storeCat->id));

        // Platform category remains unchanged
        $this->assertEquals($originalPlatformCategoryId, $freshProduct->category_id);
    }
}

