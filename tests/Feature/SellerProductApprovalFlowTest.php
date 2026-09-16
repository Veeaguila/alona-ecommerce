<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerProductApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_products_require_admin_approval_before_buyer_visibility(): void
    {
        $category = Category::create([
            'name' => 'Home Essentials',
            'slug' => 'home-essentials',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Seller Store',
        ]);

        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $this->actingAs($seller)
            ->post('/seller/products', [
                'name' => 'Approved Lamp',
                'category_id' => $category->id,
                'price' => 299.99,
                'stock' => 25,
                'description' => 'A lamp that must be reviewed first.',
                'status' => 'approved',
            ])
            ->assertRedirect('/seller/products');

        $product = Product::first();

        $this->assertNotNull($product);
        $this->assertSame('pending', $product->status);

        $this->actingAs($admin)
            ->patch('/admin/compliance/'.$product->id.'/status', [
                'action' => 'approve',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $product->fresh()->status);

        $this->actingAs($buyer)
            ->get('/buyer/products')
            ->assertOk()
            ->assertSee('Approved Lamp');
    }
}
