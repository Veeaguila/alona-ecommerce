<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
            'email' => 'admin-' . uniqid() . '@example.com',
        ]);
    }

    protected function createBuyer(): User
    {
        return User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'active',
        ]);
    }

    protected function createSeller(): User
    {
        return User::factory()->create([
            'usertype' => 'seller',
            'status' => 'active',
            'store_name' => 'Test Tech Store',
        ]);
    }

    public function test_category_model_hierarchy_relationships_and_helpers(): void
    {
        $parent = Category::create([
            'name' => 'Parent Category Test',
            'slug' => 'parent-category-test',
            'is_active' => true,
        ]);

        $child1 = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Child Category 1 Test',
            'slug' => 'child-category-1-test',
            'is_active' => true,
        ]);

        $child2 = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Child Category 2 Test',
            'slug' => 'child-category-2-test',
            'is_active' => true,
        ]);

        $this->assertTrue($parent->isRoot());
        $this->assertFalse($parent->isSubcategory());
        $this->assertFalse($child1->isRoot());
        $this->assertTrue($child1->isSubcategory());

        $this->assertEquals($parent->id, $child1->parent->id);
        $this->assertCount(2, $parent->children);
        $this->assertCount(2, $parent->subcategories);

        $descendants = $parent->getDescendantIds();
        $this->assertContains($parent->id, $descendants);
        $this->assertContains($child1->id, $descendants);
        $this->assertContains($child2->id, $descendants);

        $rootCats = Category::root()->pluck('id')->all();
        $this->assertContains($parent->id, $rootCats);
        $this->assertNotContains($child1->id, $rootCats);

        $subCats = Category::onlySubcategories()->pluck('id')->all();
        $this->assertContains($child1->id, $subCats);
        $this->assertNotContains($parent->id, $subCats);
    }

    public function test_admin_can_view_and_create_subcategories(): void
    {
        $admin = $this->createAdmin();

        $root = Category::create([
            'name' => 'Admin Parent Test',
            'slug' => 'admin-parent-test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.categories'));
        $response->assertStatus(200);

        // Create subcategory under root
        $storeResponse = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'parent_id' => $root->id,
            'name' => 'Admin Subcategory Test',
            'slug' => 'admin-subcategory-test',
            'is_active' => true,
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'parent_id' => $root->id,
            'name' => 'Admin Subcategory Test',
            'slug' => 'admin-subcategory-test',
        ]);
    }

    public function test_admin_cannot_set_category_as_its_own_parent(): void
    {
        $admin = $this->createAdmin();

        $cat = Category::create([
            'name' => 'Self Parent Test',
            'slug' => 'self-parent-test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.categories.update', $cat->id), [
            'parent_id' => $cat->id,
            'name' => 'Self Parent Test',
            'slug' => 'self-parent-test',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_admin_cannot_delete_category_with_subcategories(): void
    {
        $admin = $this->createAdmin();

        $parent = Category::create([
            'name' => 'Delete Guard Parent Test',
            'slug' => 'delete-guard-parent-test',
            'is_active' => true,
        ]);

        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Delete Guard Child Test',
            'slug' => 'delete-guard-child-test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.categories.destroy', $parent->id));
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_buyer_categories_page_returns_hierarchy(): void
    {
        $buyer = $this->createBuyer();

        $parent = Category::create([
            'name' => 'Buyer Cat Hierarchy Test',
            'slug' => 'buyer-cat-hierarchy-test',
            'is_active' => true,
        ]);

        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Buyer Subcat Hierarchy Test',
            'slug' => 'buyer-subcat-hierarchy-test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($buyer)->get(route('buyer.categories'));
        $response->assertStatus(200);
    }

    public function test_buyer_category_show_includes_subcategory_products_and_filters(): void
    {
        $buyer = $this->createBuyer();
        $seller = $this->createSeller();

        $parent = Category::create([
            'name' => 'Electronics Test Parent',
            'slug' => 'electronics-test-parent',
            'is_active' => true,
        ]);

        $sub = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Headphones Test Sub',
            'slug' => 'headphones-test-sub',
            'is_active' => true,
        ]);

        // Product in subcategory
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $sub->id,
            'name' => 'Wireless Pro Headphones',
            'slug' => 'wireless-pro-headphones-' . uniqid(),
            'price' => 2500.00,
            'stock' => 10,
            'status' => 'approved',
            'description' => 'Great headphones',
        ]);

        // Viewing parent category should include products from subcategory
        $response = $this->actingAs($buyer)->get(route('buyer.category.show', $parent->slug));
        $response->assertStatus(200);

        // Filtering by subcategory directly on parent category page
        $filterResponse = $this->actingAs($buyer)->get(
            route('buyer.category.show', [$parent->slug, 'subcategory' => $sub->slug])
        );
        $filterResponse->assertStatus(200);

        // Viewing subcategory page directly
        $subResponse = $this->actingAs($buyer)->get(route('buyer.category.show', $sub->slug));
        $subResponse->assertStatus(200);
    }
}
