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
use Tests\TestCase;

class VariantCatalogSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VariantCatalogSeeder::class);
    }

    public function test_variant_types_and_master_colors_are_seeded(): void
    {
        $colorType = VariantType::where('slug', 'color')->first();
        $this->assertNotNull($colorType);
        $this->assertEquals('color_swatch', $colorType->display_type);

        $black = VariantValue::where('variant_type_id', $colorType->id)->where('value', 'Black')->first();
        $this->assertNotNull($black);
        $this->assertEquals('#000000', $black->hex_color);

        $sizeType = VariantType::where('slug', 'size-international')->first();
        $this->assertNotNull($sizeType);
        $this->assertTrue($sizeType->values()->where('value', 'XL')->exists());
    }

    public function test_categories_have_mapped_predefined_variant_types(): void
    {
        $mensApparel = Category::where('slug', 'mens-apparel')->first();
        $this->assertNotNull($mensApparel);

        $types = $mensApparel->getEffectiveVariantTypes();
        $this->assertNotEmpty($types);
        $this->assertTrue($types->contains('slug', 'size-international'));
        $this->assertTrue($types->contains('slug', 'color'));
    }

    public function test_automatic_product_sku_generation(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $cat = Category::first();

        $product1 = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $cat->id,
            'name' => 'Classic Cotton T-Shirt',
            'price' => 299.00,
            'stock' => 50,
        ]);

        $this->assertNotEmpty($product1->sku);
        $this->assertStringStartsWith('ALN-', $product1->sku);

        // Ensure SKU is read-only and remains unchanged on update
        $originalSku = $product1->sku;
        $product1->update(['name' => 'Classic Cotton T-Shirt Updated', 'sku' => 'TRY-TO-CHANGE']);
        $this->assertEquals($originalSku, $product1->fresh()->sku);

        // Ensure next product gets distinct unique SKU
        $product2 = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $cat->id,
            'name' => 'Classic Denim Jeans',
            'price' => 599.00,
            'stock' => 20,
        ]);

        $this->assertNotEquals($product1->sku, $product2->sku);
        $this->assertStringStartsWith('ALN-', $product2->sku);
    }

    public function test_variant_combination_attributes_and_label(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $cat = Category::first();

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $cat->id,
            'name' => 'Smartphone Pro 5G',
            'price' => 15000.00,
            'stock' => 10,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Black',
            'size' => '256GB',
            'variant_attributes' => [
                'Color' => 'Black',
                'Storage' => '256GB',
                'RAM' => '12GB',
            ],
            'price' => 18000.00,
            'stock' => 5,
        ]);

        $this->assertNotNull($variant->combination_key);
        $this->assertStringContainsString('color:black', $variant->combination_key);
        $this->assertEquals('Black - 256GB - 12GB', $variant->label());
    }
}
