<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEnterpriseErpFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_manage_categories(): void
    {
        $this->actingAs($this->admin);

        // 1. View categories
        $this->get(route('admin.categories'))->assertOk();

        // 2. Create category
        $createRes = $this->post(route('admin.categories.store'), [
            'name' => 'Bohol Handicrafts',
            'slug' => 'bohol-handicrafts',
            'is_active' => true,
        ]);
        $createRes->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'name' => 'Bohol Handicrafts',
            'slug' => 'bohol-handicrafts',
            'is_active' => 1,
        ]);

        $category = Category::where('slug', 'bohol-handicrafts')->firstOrFail();

        // Verify audit log recorded
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'category_created',
        ]);

        // 3. Update category
        $updateRes = $this->patch(route('admin.categories.update', $category), [
            'name' => 'Bohol Handicrafts & Souvenirs',
            'slug' => 'bohol-handicrafts-souvenirs',
            'is_active' => false,
        ]);
        $updateRes->assertRedirect();
        $this->assertSame('Bohol Handicrafts & Souvenirs', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);

        // 4. Delete category
        $delRes = $this->delete(route('admin.categories.destroy', $category));
        $delRes->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_admin_can_manage_platform_vouchers(): void
    {
        $this->actingAs($this->admin);

        // 1. View vouchers
        $this->get(route('admin.vouchers'))->assertOk();

        // 2. Create platform voucher
        $createRes = $this->post(route('admin.vouchers.store'), [
            'code' => 'ALONASAVE50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'usage_limit' => 500,
            'per_customer_limit' => 2,
            'is_active' => true,
        ]);
        $createRes->assertRedirect();

        $this->assertDatabaseHas('vouchers', [
            'code' => 'ALONASAVE50',
            'is_platform' => 1,
            'value' => 50.00,
        ]);

        $voucher = Voucher::where('code', 'ALONASAVE50')->firstOrFail();

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'voucher_created',
        ]);

        // 3. Update voucher
        $updateRes = $this->patch(route('admin.vouchers.update', $voucher), [
            'code' => 'ALONASAVE50',
            'type' => 'fixed',
            'value' => 60.00,
            'min_spend' => 350.00,
            'is_active' => true,
        ]);
        $updateRes->assertRedirect();
        $this->assertEquals(60.00, (float) $voucher->fresh()->value);

        // 4. Delete voucher
        $delRes = $this->delete(route('admin.vouchers.destroy', $voucher));
        $delRes->assertRedirect();
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_admin_activity_audit_logs_are_viewable(): void
    {
        $this->actingAs($this->admin);

        AdminAuditLog::record(
            $this->admin,
            'security_policy_updated',
            'Admin updated two-factor authentication requirements.'
        );

        $response = $this->get(route('admin.audit-logs'));
        $response->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'security_policy_updated',
        ]);
    }

    public function test_admin_can_manage_shipping_rates_and_delivery_zones(): void
    {
        $this->actingAs($this->admin);

        // 1. View delivery zones
        $this->get(route('admin.shipping-rates'))->assertOk();

        // 2. Create zone
        $createRes = $this->post(route('admin.shipping-rates.store'), [
            'name' => 'Tubigon Port Hub Zone',
            'province' => 'Bohol',
            'base_shipping_rate' => 60.00,
            'per_kg_rate' => 12.00,
            'estimated_days' => '1-2 Days',
            'is_active' => true,
        ]);
        $createRes->assertRedirect();

        $zone = DeliveryZone::where('name', 'Tubigon Port Hub Zone')->firstOrFail();
        $this->assertEquals(60.00, (float) $zone->base_shipping_rate);

        // 3. Update rate
        $updateRes = $this->patch(route('admin.shipping-rates.update', $zone), [
            'name' => 'Tubigon Port Hub Zone',
            'province' => 'Bohol',
            'base_shipping_rate' => 65.00,
            'per_kg_rate' => 12.00,
            'estimated_days' => '1-2 Days',
            'is_active' => true,
        ]);
        $updateRes->assertRedirect();
        $this->assertEquals(65.00, (float) $zone->fresh()->base_shipping_rate);

        // 4. Delete zone
        $delRes = $this->delete(route('admin.shipping-rates.destroy', $zone));
        $delRes->assertRedirect();
        $this->assertDatabaseMissing('delivery_zones', ['id' => $zone->id]);
    }

    public function test_admin_can_export_sales_and_commission_reports(): void
    {
        $this->actingAs($this->admin);

        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Exportable Item',
            'slug' => 'exportable-item-' . uniqid(),
            'price' => 300.00,
            'stock' => 20,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-EXP-001',
            'shipping_address' => 'Tagbilaran City',
            'subtotal' => 300.00,
            'total' => 300.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 300.00,
            'quantity' => 1,
            'status' => 'completed',
        ]);

        // Trigger commission recording
        $order->update(['status' => 'completed']);

        // 1. Export sales summary CSV
        $salesExportRes = $this->get(route('admin.reports.sales-summary.export'));
        $salesExportRes->assertOk();
        $salesExportRes->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 2. Export commission report CSV
        $commissionExportRes = $this->get(route('admin.reports.commission.export'));
        $commissionExportRes->assertOk();
        $commissionExportRes->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}

