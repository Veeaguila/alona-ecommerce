<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminErpRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_erp_routes_are_available_for_approved_admin_users(): void
    {
        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users'))->assertOk();
        $this->get(route('admin.applications'))->assertOk();
        $this->get(route('admin.compliance'))->assertOk();
        $this->get(route('admin.complaints'))->assertOk();
        $this->get(route('admin.settings'))->assertOk();
        $this->get(route('admin.messages'))->assertOk();
    }
}
