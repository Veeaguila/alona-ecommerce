<?php

namespace Tests\Feature\Logistics;

use App\Models\{Category, Order, Parcel, Product, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParcelMonitoringSearchTest extends TestCase
{
    use RefreshDatabase;

    private SortingCenter $center;
    private SortingCenter $foreign;
    private User $buyer;
    private Order $order;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->center = $this->center('Owned');
        $this->foreign = $this->center('Foreign');
        $this->buyer = User::factory()->create(['usertype' => 'buyer', 'name' => "Maria O'Neil"]);
        $seller = User::factory()->create(['usertype' => 'seller']);
        $category = Category::create(['name' => 'Test', 'slug' => 'monitoring-test', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id,
            'name' => 'Fixture', 'slug' => 'monitoring-product', 'price' => 100, 'stock' => 20, 'status' => 'approved']);
        $this->order = Order::create(['user_id' => $this->buyer->id, 'order_number' => 'ALONA-SEARCH-123',
            'subtotal' => 100, 'total' => 100, 'shipping_address' => 'Fixture address', 'payment_method' => 'cod', 'status' => 'packed']);
        Sanctum::actingAs($this->center->user);
    }

    public function test_exact_tracking_parent_id_order_number_and_case_insensitive_full_buyer_name(): void
    {
        $owned = $this->parcel('ITEM-SEARCH-001');
        $this->parcel('FOREIGN-SEARCH', $this->foreign);
        $otherBuyer = User::factory()->create(['usertype' => 'buyer', 'name' => 'Someone Else']);
        $this->parcel('ITEM-UNRELATED', buyer: $otherBuyer, linked: false);

        foreach (['ITEM-SEARCH-001', (string) $this->order->id, 'ALONA-SEARCH-123', "mARIA o'NEIL", '  ITEM-SEARCH-001  '] as $term) {
            $this->search($term)->assertOk()->assertJsonPath('data.total', 1)
                ->assertJsonPath('data.data.0.id', $owned->id);
        }
    }

    public function test_no_partial_wildcard_injection_or_numeric_coercion_matches(): void
    {
        $this->parcel('ITEM-SEARCH-001');
        foreach (['ITEM-SEARCH', 'ALONA-SEARCH', 'Maria', '%', '_', "' OR 1=1 --", $this->order->id.'oops', '+'.$this->order->id, '999999999999999999999999999999'] as $term) {
            $this->search($term)->assertOk()->assertJsonPath('data.total', 0);
        }
    }

    public function test_all_search_branches_remain_scoped_to_company_and_status(): void
    {
        $this->parcel('ONLY-FOREIGN', $this->foreign);
        $owned = $this->parcel('OWNED-DELIVERED', status: 'DELIVERED');
        $this->parcel('OWNED-HUB');
        $this->search('ONLY-FOREIGN')->assertOk()->assertJsonPath('data.total', 0);
        foreach ([(string) $this->order->id, $this->order->order_number, $this->buyer->name] as $term) {
            $this->search($term, ['status' => 'DELIVERED'])->assertOk()->assertJsonPath('data.total', 1)
                ->assertJsonPath('data.data.0.id', $owned->id);
        }
        $this->search('OWNED-HUB', ['status' => 'DELIVERED'])->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_legacy_unlinked_parcels_still_match_tracking_and_buyer(): void
    {
        $owned = $this->parcel('LEGACY-TRACKING', linked: false);
        foreach (['LEGACY-TRACKING', $this->buyer->name] as $term) {
            $this->search($term)->assertOk()->assertJsonPath('data.data.0.id', $owned->id);
        }
        $this->search($this->order->order_number)->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_parent_search_keeps_all_owned_items_and_pagination_query(): void
    {
        foreach (range(1, 12) as $i) $this->parcel('PAGED-'.$i);
        $this->parcel('FOREIGN-PAGED', $this->foreign);
        $response = $this->search($this->order->order_number, ['status' => 'AT_SORTING_CENTER', 'page' => 2])
            ->assertOk()->assertJsonPath('data.total', 12)->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.current_page', 2)->assertJsonCount(2, 'data.data');
        parse_str(parse_url($response->json('data.prev_page_url'), PHP_URL_QUERY), $query);
        $this->assertSame($this->order->order_number, $query['search']);
        $this->assertSame('AT_SORTING_CENTER', $query['status']);
        $this->assertSame('1', $query['page']);
    }

    public function test_invalid_search_shapes_and_length_return_visible_validation_errors(): void
    {
        foreach ([['invalid'], str_repeat('a', 256)] as $term) {
            $this->search($term)->assertUnprocessable()->assertJsonValidationErrors('search');
        }
    }

    public function test_empty_search_preserves_existing_workflow_without_mutation(): void
    {
        $parcel = $this->parcel('UNCHANGED');
        $before = $parcel->fresh()->getAttributes();
        $this->search('  ')->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame($before, $parcel->fresh()->getAttributes());
    }

    private function search(mixed $term, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/sorting-center/parcels/monitoring?'.http_build_query(['search' => $term] + $extra));
    }

    private function center(string $name): SortingCenter
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        return SortingCenter::create(['user_id' => $user->id, 'business_name' => $name, 'approval_status' => 'approved']);
    }

    private function parcel(string $reference, ?SortingCenter $center = null, string $status = 'AT_SORTING_CENTER', ?User $buyer = null, bool $linked = true): Parcel
    {
        $item = $linked ? $this->order->items()->create(['product_id' => $this->product->id,
            'product_name' => 'Fixture', 'price' => 100, 'quantity' => 1, 'status' => 'packed']) : null;
        return Parcel::create(['order_id' => $reference, 'order_item_id' => $item?->id,
            'seller_id' => $this->product->seller_id, 'buyer_id' => ($buyer ?? $this->buyer)->id,
            'sorting_center_id' => ($center ?? $this->center)->id, 'status' => $status,
            'delivery_province' => 'Metro Manila', 'delivery_municipality' => 'Quezon City', 'delivery_barangay' => 'Bagumbayan']);
    }
}
