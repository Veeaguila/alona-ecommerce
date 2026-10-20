<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Commission;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundLedger;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminComplaintDisputeRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_resolve_complaint_and_execute_force_refund(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Defective Item',
            'slug' => 'defective-item-' . uniqid(),
            'price' => 750.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-DISPUTE-001',
            'shipping_address' => '456 Elm St, Tagbilaran City',
            'subtotal' => 750.00,
            'total' => 750.00,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 750.00,
            'quantity' => 1,
            'status' => 'delivered',
        ]);

        // Record initial commission
        Commission::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'sale_amount' => 750.00,
            'commission_rate' => 10.00,
            'commission_amount' => 75.00,
            'seller_amount' => 675.00,
            'status' => 'completed',
            'recorded_at' => now(),
        ]);

        $complaint = Complaint::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'subject' => 'Received completely broken item',
            'type' => 'damaged_goods',
            'description' => 'The product arrived shattered and seller refuses to answer.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.complaints.resolve', $complaint),
            [
                'status' => 'resolved',
                'resolution' => 'Full force-refund granted to buyer due to verified damage in transit.',
                'issue_refund' => true,
                'refund_amount' => 750.00,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Verify complaint status
        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertSame($admin->id, $complaint->fresh()->reviewed_by);

        // Verify Refund Ledger record
        $this->assertDatabaseHas('refund_ledgers', [
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'refunded_amount' => 750.00,
            'status' => 'completed',
        ]);

        // Verify Order status
        $this->assertSame('refunded', $order->fresh()->refund_status);
        $this->assertEquals(750.00, (float) $order->fresh()->refund_amount);
        $this->assertSame('refunded', $order->fresh()->payment_status);

        // Verify Commission cancelled
        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'status' => 'cancelled',
        ]);

        // Verify Buyer Notification
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $buyer->id,
            'type' => 'refund',
        ]);

        // Verify Seller Notification
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $seller->id,
            'type' => 'refund',
        ]);
    }

    public function test_admin_can_resolve_complaint_without_refund(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);

        $complaint = Complaint::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'subject' => 'Inquiry about packaging',
            'type' => 'inquiry',
            'description' => 'Just curious why packaging was plain box.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.complaints.resolve', $complaint),
            [
                'status' => 'resolved',
                'resolution' => 'Seller confirmed eco-friendly packaging policy.',
                'issue_refund' => false,
            ]
        );

        $response->assertRedirect();
        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertDatabaseCount('refund_ledgers', 0);
    }
}

