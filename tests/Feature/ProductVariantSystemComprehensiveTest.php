<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantType;
use App\Models\VariantValue;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VariantCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantSystemComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(CategorySeeder::class);
        $this->seed(VariantCatalogSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@alona.ph',
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $this->seller = User::factory()->create([
            'name' => 'Alona Tech Store',
            'email' => 'seller@alona.ph',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Alona Official Gadgets',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Maria Buyer',
            'email' => 'maria@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);
    }

    private function createFakePng(string $name = 'item.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 600);
    }

    public function test_variant_catalog_seeder_populates_master_colors_and_categories_a_through_q(): void
    {
        // Verify Master Colors
        $colorType = VariantType::where('name', 'Color')->with('values')->first();
        $this->assertNotNull($colorType);
        $this->assertSame('color_swatch', $colorType->display_type);

        $colorNames = $colorType->values->pluck('value')->toArray();
        $expectedColors = [
            'Black', 'White', 'Gray', 'Charcoal', 'Silver',
            'Red', 'Maroon', 'Burgundy', 'Blue', 'Navy',
            'Sky Blue', 'Green', 'Olive', 'Mint', 'Yellow',
            'Mustard', 'Orange', 'Pink', 'Rose', 'Purple',
            'Lavender', 'Brown', 'Beige', 'Cream', 'Khaki',
            'Gold', 'Multicolor'
        ];
        foreach ($expectedColors as $expected) {
            $this->assertContains($expected, $colorNames, "Color {$expected} must exist in master color catalog.");
        }

        // Verify Variant Types across categories
        $expectedTypes = [
            'Size (International)', 'Fit', 'Sleeve Length',
            'Shoe Size (EU)', 'RAM (Mobile & Gadgets)', 'Storage (Mobile & Gadgets)',
            'Processor Family', 'Display Size', 'Headphone Style',
            'Furniture Material', 'Material',
            'Cosmetics Volume',
            'Jewelry Metal / Material', 'Vehicle Brand Compatibility'
        ];
        foreach ($expectedTypes as $type) {
            $this->assertDatabaseHas('variant_types', ['name' => $type]);
        }

        // Verify categories have mapped variant types
        $apparelCat = Category::where('slug', 'mens-apparel')->first();
        $this->assertNotNull($apparelCat);
        $apparelTypes = $apparelCat->variantTypes->pluck('name')->toArray();
        $this->assertContains('Color', $apparelTypes);
        $this->assertContains('Size (International)', $apparelTypes);
    }

    public function test_super_admin_can_manage_variant_catalog_and_category_mappings(): void
    {
        $this->actingAs($this->admin);

        // 1. Visit Catalog Dashboard
        $response = $this->get(route('admin.variants'));
        $response->assertStatus(200);

        // 2. Create new variant type
        $typeRes = $this->post(route('admin.variants.types.store'), [
            'name' => 'Warranty Duration',
            'slug' => 'warranty-duration',
            'display_type' => 'pills',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $typeRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('variant_types', ['slug' => 'warranty-duration']);

        $warrantyType = VariantType::where('slug', 'warranty-duration')->first();

        // 3. Add values to variant type
        $valRes = $this->post(route('admin.variants.values.store', $warrantyType->id), [
            'value' => '1 Year Official Warranty',
            'label' => '1 Year',
            'sort_order' => 1,
        ]);
        $valRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('variant_values', [
            'variant_type_id' => $warrantyType->id,
            'value' => '1 Year Official Warranty',
        ]);

        // 4. Toggle active status
        $toggleRes = $this->patch(route('admin.variants.types.toggle', $warrantyType->id));
        $toggleRes->assertSessionHasNoErrors();
        $this->assertFalse((bool) $warrantyType->fresh()->is_active);

        // 5. Update Category Mappings
        $category = Category::create([
            'name' => 'Smart Home Appliances',
            'slug' => 'smart-home',
            'is_active' => true,
        ]);

        $syncRes = $this->patch(route('admin.categories.variant-mappings', $category->id), [
            'variant_type_ids' => [$warrantyType->id],
        ]);
        $syncRes->assertSessionHasNoErrors();
        $this->assertTrue($category->fresh()->variantTypes->contains($warrantyType->id));

        // 6. Coverage Report
        $reportRes = $this->get(route('admin.variants.coverage-report'));
        $reportRes->assertStatus(200);
        $reportRes->assertJsonStructure([
            'total_categories',
            'covered',
            'covered_categories',
            'coverage_percentage',
            'categories',
        ]);
    }

    public function test_seller_can_fetch_category_variants_and_auto_sku_preview(): void
    {
        $this->actingAs($this->seller);

        $phonesCategory = Category::where('slug', 'mobiles-gadgets')->first();
        $this->assertNotNull($phonesCategory);

        // Seller API to get category-approved variant types
        $response = $this->get(route('seller.categories.variants', $phonesCategory->id));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'category_id',
            'category_name',
            'category' => ['id', 'name', 'slug'],
            'variant_types' => [
                '*' => ['id', 'name', 'slug', 'display_type', 'values'],
            ],
        ]);

        $types = collect($response->json('variant_types'))->pluck('name')->toArray();
        $this->assertContains('RAM (Mobile & Gadgets)', $types);
        $this->assertContains('Storage (Mobile & Gadgets)', $types);

        // Auto-SKU Preview on Create Page
        $createRes = $this->get(route('seller.products.create'));
        $createRes->assertStatus(200);
        $skuPreview = $createRes->viewData('page')['props']['autoSkuPreview'];
        $this->assertMatchesRegularExpression('/^ALN-\d{6}$/', $skuPreview);
    }

    public function test_product_creation_with_predefined_variant_combinations_and_immutable_sku(): void
    {
        $this->actingAs($this->seller);

        $laptopCat = Category::where('slug', 'laptops-computers')->first();
        $this->assertNotNull($laptopCat);

        $postData = [
            'name' => 'Alona UltraBook Pro 15',
            'category_id' => $laptopCat->id,
            'price' => 45000.00,
            'stock' => 0,
            'weight' => 1.8,
            'description' => 'High performance laptop with aluminum chassis and premium display.',
            'status' => 'pending',
            'image' => $this->createFakePng('ultrabook.png'),
            'variation_types' => [
                ['name' => 'RAM (Computer & Laptop)', 'options' => ['16GB', '32GB']],
                ['name' => 'Internal Storage (Computer)', 'options' => ['512GB', '1TB']],
            ],
            'variants' => [
                [
                    'variant_attributes' => ['RAM (Computer & Laptop)' => '16GB', 'Internal Storage (Computer)' => '512GB'],
                    'price' => 45000.00,
                    'stock' => 10,
                    'weight' => 1.8,
                ],
                [
                    'variant_attributes' => ['RAM (Computer & Laptop)' => '16GB', 'Internal Storage (Computer)' => '1TB'],
                    'price' => 48000.00,
                    'stock' => 5,
                    'weight' => 1.8,
                ],
                [
                    'variant_attributes' => ['RAM (Computer & Laptop)' => '32GB', 'Internal Storage (Computer)' => '1TB'],
                    'price' => 55000.00,
                    'stock' => 8,
                    'weight' => 1.8,
                ],
            ],
        ];

        $response = $this->post(route('seller.products.store'), $postData);
        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Alona UltraBook Pro 15')->first();
        $this->assertNotNull($product);

        // 1. Verify Auto-SKU was assigned and format is ALN-XXXXXX
        $this->assertNotNull($product->sku);
        $this->assertMatchesRegularExpression('/^ALN-\d{6}$/', $product->sku);
        $initialSku = $product->sku;

        // 2. Verify total stock was automatically synchronized from variants (10 + 5 + 8 = 23)
        $this->assertEquals(23, $product->stock);

        // 3. Verify variant combinations
        $this->assertCount(3, $product->variants);
        $variant1 = $product->variants()->where('price', 45000.00)->first();
        $this->assertNotNull($variant1);
        $this->assertSame(['RAM (Computer & Laptop)' => '16GB', 'Internal Storage (Computer)' => '512GB'], $variant1->variant_attributes);
        $this->assertSame(10, $variant1->stock);
        $this->assertNotNull($variant1->combination_key);

        // 4. Verify SKU Immutability: Seller tries to alter SKU on update
        $updateResponse = $this->patch(route('seller.products.update', $product->id), [
            'name' => 'Alona UltraBook Pro 15 2026 Edition',
            'category_id' => $laptopCat->id,
            'price' => 46000.00,
            'stock' => 23,
            'weight' => 1.8,
            'description' => 'High performance laptop with aluminum chassis and premium display updated.',
            'status' => 'pending',
            'sku' => 'CUSTOM-HACKED-SKU', // Attacker or seller trying to overwrite SKU
            'variants' => [
                [
                    'id' => $variant1->id,
                    'variant_attributes' => ['RAM (Computer & Laptop)' => '16GB', 'Internal Storage (Computer)' => '512GB'],
                    'price' => 46000.00,
                    'stock' => 12, // Increased stock
                ],
            ],
        ]);
        $updateResponse->assertSessionHasNoErrors();

        $freshProduct = $product->fresh();
        $this->assertSame($initialSku, $freshProduct->sku, 'Product SKU must remain immutable.');
        $this->assertSame(46000.0, (float) $variant1->fresh()->price);
        $this->assertSame(12, $variant1->fresh()->stock);
    }

    public function test_single_product_without_variants_functions_normally(): void
    {
        $this->actingAs($this->seller);

        $category = Category::create([
            'name' => 'Special Books',
            'slug' => 'special-books',
            'is_active' => true,
        ]);

        $response = $this->post(route('seller.products.store'), [
            'name' => 'Laravel Enterprise Architecture Book',
            'category_id' => $category->id,
            'price' => 1200.00,
            'stock' => 50,
            'weight' => 0.8,
            'description' => 'Comprehensive enterprise development manual for high scale systems.',
            'status' => 'pending',
            'image' => $this->createFakePng('book.png'),
            'variants' => [],
        ]);
        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Laravel Enterprise Architecture Book')->first();
        $this->assertNotNull($product);
        $this->assertMatchesRegularExpression('/^ALN-\d{6}$/', $product->sku);
        $this->assertSame(50, $product->stock);
        $this->assertCount(0, $product->variants);
    }

    public function test_buyer_product_page_renders_variants_and_cart_integration(): void
    {
        $category = Category::where('slug', 'mens-apparel')->first();
        $this->assertNotNull($category);

        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Premium Cotton Polo',
            'slug' => 'premium-cotton-polo',
            'price' => 899.00,
            'stock' => 15,
            'weight' => 0.3,
            'status' => 'approved',
            'description' => 'Breathable organic cotton polo shirt with modern slim fit cut.',
            'variation_types' => [
                ['name' => 'Color', 'options' => ['Navy', 'Burgundy']],
                ['name' => 'Size (International)', 'options' => ['M', 'L']],
            ],
        ]);

        $vNavyM = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Navy',
            'size' => 'M',
            'variant_attributes' => ['Color' => 'Navy', 'Size (International)' => 'M'],
            'combination_key' => 'color:navy|size (international):m',
            'price' => 899.00,
            'stock' => 10,
            'weight' => 0.3,
            'is_active' => true,
        ]);

        $vBurgundyL = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Burgundy',
            'size' => 'L',
            'variant_attributes' => ['Color' => 'Burgundy', 'Size (International)' => 'L'],
            'combination_key' => 'color:burgundy|size (international):l',
            'price' => 949.00,
            'stock' => 0, // Out of stock combination
            'weight' => 0.3,
            'is_active' => true,
        ]);

        // Buyer views product details
        $this->actingAs($this->buyer);
        $viewRes = $this->get(route('buyer.product', $product));
        $viewRes->assertStatus(200);

        $viewData = $viewRes->viewData('page')['props']['product'];
        $this->assertCount(2, $viewData['variants']);
        $variantPrices = collect($viewData['variants'])->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $this->assertContains(899.0, $variantPrices);
        $this->assertContains(949.0, $variantPrices);

        // Buyer adds Navy / M variant to cart
        $cartRes = $this->post(route('buyer.cart.store', $product->id), [
            'product_variant_id' => $vNavyM->id,
            'quantity' => 2,
        ]);
        $cartRes->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'product_variant_id' => $vNavyM->id,
            'quantity' => 2,
        ]);
    }
}
