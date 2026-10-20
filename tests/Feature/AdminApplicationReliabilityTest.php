<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminApplicationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_approval_sends_email_and_creates_in_app_notification(): void
    {
        Mail::shouldReceive('raw')->once();

        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'pending',
            'email' => 'seller@example.com',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.applications.approve', $seller));

        $response->assertRedirect();
        $this->assertSame('approved', $seller->fresh()->status);

        // Verify in-app notification
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $seller->id,
            'type' => 'application_decision',
        ]);
    }

    public function test_smtp_outage_does_not_fail_silently_and_persists_in_app_notification(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'pending',
            'email' => 'outage@example.com',
        ]);

        // Simulate SMTP connection timeout or outage
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Connection could not be established with host mail.example.com'));

        $response = $this->actingAs($admin)->patch(
            route('admin.applications.reject', $seller),
            ['reason' => 'Business permit expired.']
        );

        $response->assertRedirect();
        $this->assertSame('rejected', $seller->fresh()->status);
        $this->assertSame('Business permit expired.', $seller->fresh()->rejection_reason);

        // Admin received transparent notice about the email failure
        $statusMsg = session('status');
        $this->assertStringContainsString('Seller application rejected', $statusMsg);
        $this->assertStringContainsString('Notice: Decision email could not be delivered', $statusMsg);

        // In-app notification still persisted for seller
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $seller->id,
            'type' => 'application_decision',
        ]);
    }
}

