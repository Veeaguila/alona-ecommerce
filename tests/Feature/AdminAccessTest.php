<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_exposes_pending_seller_applications(): void
    {
        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
            'email' => 'admin@example.com',
        ]);

        $pendingSeller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'pending',
            'email' => 'pending-seller@example.com',
            'name' => 'Pending Seller',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.seller-applications'))
            ->assertOk()
            ->assertSee('pending-seller@example.com');

        $this->assertNotNull($pendingSeller);
    }

    public function test_non_admin_users_are_blocked_from_the_admin_area(): void
    {
        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'email' => 'buyer@example.com',
        ]);

        $this->actingAs($buyer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
