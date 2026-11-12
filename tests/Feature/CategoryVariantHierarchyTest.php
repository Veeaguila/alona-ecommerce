<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VariantCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryVariantHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $this->seed(CategorySeeder::class);
        $this->seed(VariantCatalogSeeder::class);
    }

    public function test_categories_and_subcategories_are_properly_structured(): void
    {
        // Check root categories
        $rootSlugs = [
            'mens-fashion',
            'womens-fashion',
            'babies-kids',
            'electronics',
            'laptops-computers',
            'home-living',
            'beauty',
            'groceries',
            'jewelry',
            'sports-travel',
            'toys-games-collectibles',
            'hobbies-stationery',
            'motors',
            'pet-care',
        ];

        foreach ($rootSlugs as $slug) {
            $root = Category::where('slug', $slug)->first();
            $this->assertNotNull($root, "Root category [{$slug}] should exist");
            $this->assertNull($root->parent_id, "Root category [{$slug}] should have parent_id = null");
            $this->assertTrue($root->subcategories->count() > 0, "Root category [{$slug}] should have subcategories");
        }

        // Check specific subcategories
        $mensShoes = Category::where('slug', 'mens-shoes')->first();
        $this->assertNotNull($mensShoes);
        $this->assertEquals('mens-fashion', $mensShoes->parent->slug);

        $pcComponents = Category::where('slug', 'pc-components')->first();
        $this->assertNotNull($pcComponents);
        $this->assertEquals('laptops-computers', $pcComponents->parent->slug);

        $snacks = Category::where('slug', 'snacks')->first();
        $this->assertNotNull($snacks);
        $this->assertEquals('groceries', $snacks->parent->slug);
    }

    public function test_all_categories_and_subcategories_have_effective_variant_types(): void
    {
        $categories = Category::all();
        $this->assertGreaterThan(25, $categories->count());

        foreach ($categories as $cat) {
            $effectiveTypes = $cat->getEffectiveVariantTypes();
            $this->assertTrue(
                $effectiveTypes->isNotEmpty(),
                "Category or subcategory [{$cat->name}] ({$cat->slug}) must have assigned or inherited variant types"
            );
        }
    }

    public function test_fashion_categories_fit_fashion_variants(): void
    {
        $mensApparel = Category::where('slug', 'mens-apparel')->first();
        $types = $mensApparel->getEffectiveVariantTypes()->pluck('slug')->toArray();

        $this->assertContains('size-international', $types);
        $this->assertContains('color', $types);
        $this->assertContains('material-fashion', $types);
        $this->assertContains('fit', $types);

        $mensShoes = Category::where('slug', 'mens-shoes')->first();
        $shoeTypes = $mensShoes->getEffectiveVariantTypes()->pluck('slug')->toArray();

        $this->assertContains('shoe-size-eu', $shoeTypes);
        $this->assertContains('shoe-size-us-men', $shoeTypes);
        $this->assertContains('material-shoes', $shoeTypes);
    }

    public function test_electronics_and_pc_categories_fit_tech_variants(): void
    {
        $mobiles = Category::where('slug', 'mobiles-gadgets')->first();
        $mobileTypes = $mobiles->getEffectiveVariantTypes()->pluck('slug')->toArray();

        $this->assertContains('ram-mobile', $mobileTypes);
        $this->assertContains('storage-mobile', $mobileTypes);
        $this->assertContains('color', $mobileTypes);

        $pc = Category::where('slug', 'pc-components')->first();
        $pcTypes = $pc->getEffectiveVariantTypes()->pluck('slug')->toArray();

        $this->assertContains('ram-component-capacity', $pcTypes);
        $this->assertContains('ram-type', $pcTypes);
        $this->assertContains('storage-interface', $pcTypes);
        $this->assertContains('psu-wattage', $pcTypes);
    }

    public function test_food_and_groceries_fit_flavor_and_weight_variants(): void
    {
        $snacks = Category::where('slug', 'snacks')->first();
        $snackTypes = $snacks->getEffectiveVariantTypes()->pluck('slug')->toArray();

        $this->assertContains('flavor', $snackTypes);
        $this->assertContains('food-weight', $snackTypes);
        $this->assertContains('spice-level', $snackTypes);

        $beverages = Category::where('slug', 'beverages')->first();
        $beverageTypes = $beverages->getEffectiveVariantTypes()->pluck('slug')->toArray();

        $this->assertContains('liquid-volume', $beverageTypes);
    }

    public function test_seller_endpoint_loads_variant_types_for_subcategories(): void
    {
        $this->actingAs($this->seller);

        $sub = Category::where('slug', 'smartwatches')->first();
        $response = $this->get(route('seller.categories.variants', $sub->id));

        $response->assertStatus(200);
        $response->assertJsonPath('category_name', $sub->name);
        
        $types = collect($response->json('variant_types'));
        $this->assertTrue($types->contains('slug', 'watch-strap'));
        $this->assertTrue($types->contains('slug', 'color'));
    }

    public function test_sync_categories_artisan_command_executes_successfully(): void
    {
        $this->artisan('variants:sync-categories')
            ->expectsOutputToContain('Starting category hierarchy and variant catalog sync')
            ->expectsOutputToContain('100%')
            ->assertExitCode(0);
    }
}
