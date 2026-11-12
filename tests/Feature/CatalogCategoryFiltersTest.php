<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogCategoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public static function audiences(): array
    {
        return [['guest'], ['buyer']];
    }

    private function audience(string $role): string
    {
        if ($role === 'buyer') {
            $this->actingAs(User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']));
        }
        return $role.'.products';
    }

    private function product(Category $category, float $price = 100, string $status = 'approved'): Product
    {
        static $sequence = 0;
        $sequence++;
        return Product::create([
            'seller_id' => User::factory()->create(['usertype' => 'seller', 'status' => 'approved'])->id,
            'category_id' => $category->id,
            'name' => 'Category item '.$sequence,
            'slug' => 'category-item-'.$sequence,
            'price' => $price,
            'stock' => 10,
            'status' => $status,
        ]);
    }

    #[DataProvider('audiences')]
    public function test_every_visible_category_filters_its_products_and_all_resets_the_filter(string $role): void
    {
        $route = $this->audience($role);
        $names = ['Action Figures & Collectibles', 'Audio', 'Auto Replacement & Parts', 'Babies & Kids',
            'Baby & Kids Clothing', 'Baby Gear & Nursery', 'Bedding', 'Beverages', 'Board Games & Puzzles',
            'Books & Magazines', 'Cameras', 'Camping & Hiking'];
        $categories = collect($names)->map(fn ($name) => Category::create([
            'name' => $name, 'slug' => 'test-catalog-'.\Illuminate\Support\Str::slug($name), 'is_active' => true,
        ]));
        foreach ($categories as $category) {
            // Expensive products must remain visible without an explicit price filter.
            $product = $this->product($category, 12000);
            $this->get(route($route, ['category' => $category->slug]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.category', $category->slug)
                    ->where('filters.max_price', null)
                    ->where('products.total', 1)
                    ->where('products.data.0.id', $product->id));
        }
        $this->product($categories->first(), 100, 'pending');
        Category::create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);
        $this->get(route($route, ['category' => 'all']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', Category::where('is_active', true)->count())
                ->where('products.total', count($names))
                ->where('filters.category', 'all'));
        $this->get(route($route, ['category' => 'unknown-category']))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    #[DataProvider('audiences')]
    public function test_parent_includes_children_and_empty_category_does_not_show_other_products(string $role): void
    {
        $route = $this->audience($role);
        $parent = Category::create(['name' => 'Audio', 'slug' => 'test-catalog-audio', 'is_active' => true]);
        $child = Category::create(['name' => 'Speakers', 'slug' => 'test-catalog-speakers', 'parent_id' => $parent->id, 'is_active' => true]);
        $empty = Category::create(['name' => 'Bedding', 'slug' => 'test-catalog-bedding', 'is_active' => true]);
        $direct = $this->product($parent);
        $nested = $this->product($child);
        $this->get(route($route, ['category' => $parent->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 2)
                ->where('products.data', fn ($items) => collect($items)->pluck('id')->sort()->values()->all() === collect([$direct->id, $nested->id])->sort()->values()->all()));
        $this->get(route($route, ['category' => $child->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.id', $nested->id));
        $this->get(route($route, ['category' => $empty->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    #[DataProvider('audiences')]
    public function test_category_keeps_explicit_search_price_sort_and_pagination(string $role): void
    {
        $route = $this->audience($role);
        $category = Category::create(['name' => 'Cameras', 'slug' => 'test-catalog-cameras', 'is_active' => true]);
        for ($i = 1; $i <= 14; $i++) $this->product($category, $i * 100);
        $this->product($category, 9000);
        $query = ['category' => 'test-catalog-cameras', 'search' => 'Category item', 'max_price' => 1400, 'sort' => 'price-low'];
        $this->get(route($route, $query))->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 14)->has('products.data', 12)
            ->where('products.data.0.price', '100.00')
            ->where('products.next_page_url', function ($url) use ($query) {
                parse_str(parse_url($url, PHP_URL_QUERY), $actual);
                return $actual['page'] === '2' && !array_diff_assoc($query, $actual);
            }));
        $this->get(route($route, $query + ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('products.current_page', 2)->has('products.data', 2)
            ->where('products.data.0.price', '1300.00'));
        $this->get(route($route, ['category' => 'test-catalog-cameras', 'search' => 'no match']))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    #[DataProvider('audiences')]
    public function test_nested_subcategory_filters_include_descendants_but_not_siblings_or_ancestors(string $role): void
    {
        $route = $this->audience($role);
        $root = Category::create(['name' => 'Nested Catalog Root', 'slug' => 'nested-catalog-root', 'is_active' => true]);
        $child = Category::create(['name' => 'Nested Speakers', 'slug' => 'nested-speakers', 'parent_id' => $root->id, 'is_active' => true]);
        $grandchild = Category::create(['name' => 'Nested Wireless', 'slug' => 'nested-wireless', 'parent_id' => $child->id, 'is_active' => true]);
        $leaf = Category::create(['name' => 'Nested Portable', 'slug' => 'nested-portable', 'parent_id' => $grandchild->id, 'is_active' => true]);
        $sibling = Category::create(['name' => 'Nested Headphones', 'slug' => 'nested-headphones', 'parent_id' => $root->id, 'is_active' => true]);
        $unrelated = Category::create(['name' => 'Unrelated Catalog', 'slug' => 'unrelated-catalog', 'is_active' => true]);
        $direct = $this->product($root);
        $speaker = $this->product($child);
        $wireless = $this->product($grandchild, 9000);
        $portable = $this->product($leaf, 12000);
        $headphones = $this->product($sibling);
        $this->product($unrelated);
        $this->product($leaf, 50, 'pending');

        foreach ([
            [$root, [$direct->id, $speaker->id, $wireless->id, $portable->id, $headphones->id]],
            [$child, [$speaker->id, $wireless->id, $portable->id]],
            [$grandchild, [$wireless->id, $portable->id]],
            [$leaf, [$portable->id]],
            [$sibling, [$headphones->id]],
        ] as [$category, $expected]) {
            $this->get(route($route, ['category' => $category->slug]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.category', $category->slug)
                    ->where('products.total', count($expected))
                    ->where('products.data', fn ($items) => collect($items)->pluck('id')->sort()->values()->all() === collect($expected)->sort()->values()->all())
                    ->where('categories', fn ($items) => collect($items)->pluck('slug')->contains($leaf->slug)));
        }

        if ($role === 'buyer') {
            foreach ([
                [$root, [], 5],
                [$child, [], 3],
                [$root, ['subcategory' => $child->slug], 3],
                [$root, ['subcategory' => $grandchild->slug], 2],
                [$root, ['subcategory' => $leaf->slug], 1],
                [$root, ['subcategory' => $unrelated->slug], 5],
            ] as [$category, $query, $count]) {
                $this->get(route('buyer.category.show', [$category->slug, ...$query]))
                    ->assertInertia(fn (Assert $page) => $page->component('Buyer/CategoryShow')->where('products.total', $count));
            }
        }
    }
}
