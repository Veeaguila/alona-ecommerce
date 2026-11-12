<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SellerProductCatalogSeeder;
use Database\Seeders\VariantCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededProductVariantLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VariantCatalogSeeder::class);
    }

    public function test_seeded_products_have_realistic_category_tailored_variant_labels(): void
    {
        $seller = User::factory()->create([
            'email' => 'techzone.ph.seller@gmail.com',
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $seeder = new SellerProductCatalogSeeder();

        $laptopCat = Category::where('slug', 'laptops-computers')->first();
        $this->assertNotNull($laptopCat);

        $laptopProduct = Product::create([
            'category_id' => $laptopCat->id,
            'seller_id' => $seller->id,
            'name' => 'MacBook Pro 14 M3',
            'price' => 110000.00,
            'stock' => 50,
            'description' => 'Apple Silicon Laptop',
        ]);

        $plan = $seeder->getVariantPlanForProduct($laptopProduct);

        $this->assertNotEmpty($plan['variation_types']);
        $this->assertEquals('RAM', $plan['variation_types'][0]['name']);
        $this->assertEquals('Storage', $plan['variation_types'][1]['name']);

        $v1 = ProductVariant::create([
            'product_id' => $laptopProduct->id,
            'color' => $plan['variants'][0]['color'],
            'size' => $plan['variants'][0]['size'],
            'variant_attributes' => $plan['variants'][0]['attributes'],
            'price' => 110000.00,
            'stock' => 25,
        ]);

        $this->assertEquals('Space Gray', $v1->color);
        $this->assertEquals('16GB / 512GB', $v1->size);
        $this->assertArrayHasKey('RAM', $v1->variant_attributes);
        $this->assertStringContainsString('512gb', $v1->combination_key);
        $this->assertNotEquals('Standard Edition', $v1->color);
    }

    public function test_relabel_artisan_command_updates_generic_variants(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller']);
        $cat = Category::where('slug', 'mens-apparel')->first();

        $product = Product::create([
            'category_id' => $cat->id,
            'seller_id' => $seller->id,
            'name' => 'Classic Slim Cotton Polo Shirt',
            'price' => 599.00,
            'stock' => 100,
            'description' => 'Pure cotton polo',
        ]);

        // Create generic old variants
        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Standard Edition',
            'size' => 'Default',
            'price' => 599.00,
            'stock' => 50,
            'sku' => 'OLD-SKU-1',
        ]);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Premium Edition',
            'size' => 'Plus',
            'price' => 699.00,
            'stock' => 50,
            'sku' => 'OLD-SKU-2',
        ]);

        $this->artisan('products:relabel-variants')
            ->expectsOutputToContain('Starting product variant relabeling process')
            ->assertExitCode(0);

        $v1->refresh();
        $v2->refresh();

        $this->assertEquals('Black', $v1->color);
        $this->assertEquals('Medium', $v1->size);
        $this->assertIsArray($v1->variant_attributes);
        $this->assertEquals('Black', $v1->variant_attributes['Color']);
        $this->assertEquals('M', $v1->variant_attributes['Size']);

        $this->assertEquals('Navy', $v2->color);
        $this->assertEquals('Large', $v2->size);
        $this->assertEquals('Navy', $v2->variant_attributes['Color']);
        $this->assertEquals('L', $v2->variant_attributes['Size']);

        $product->refresh();
        $this->assertIsArray($product->variation_types);
        $this->assertEquals('Color', $product->variation_types[0]['name']);
        $this->assertEquals('Size', $product->variation_types[1]['name']);
    }
}
