<?php

namespace Tests\Feature\Logistics;

use App\Models\PartnerApplication;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SortingCenterAdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Mail::fake();
    }

    public static function approvalRoutes(): array
    {
        return [
            'user management' => ['admin.users.status'],
            'application review' => ['admin.applications.approve'],
        ];
    }

    #[DataProvider('approvalRoutes')]
    public function test_admin_approval_restores_a_missing_legacy_center_and_allows_mobile_sorting(string $route): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $owner = User::factory()->create([
            'usertype' => 'sorting_center', 'status' => 'approved', 'name' => 'Legacy Logistics',
        ]);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($admin)->patch(route($route, $owner), ['status' => 'approved'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(1, SortingCenter::where('user_id', $owner->id)->count());
        $this->assertDatabaseHas('sorting_centers', [
            'user_id' => $owner->id, 'business_name' => 'Legacy Logistics', 'approval_status' => 'approved',
            'id_doc_url' => null, 'permit_doc_url' => null,
        ]);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner->fresh());
        $this->getJson('/api/sorting-center/parcels')->assertOk();
    }

    #[DataProvider('approvalRoutes')]
    public function test_approval_preserves_existing_company_identity_and_documents(string $route): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $owner = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'pending']);
        $center = SortingCenter::create([
            'user_id' => $owner->id, 'business_name' => 'Existing Company', 'approval_status' => 'pending',
            'id_doc_url' => 'private/original-id.pdf', 'permit_doc_url' => 'private/original-permit.pdf',
        ]);

        $this->actingAs($admin)->patch(route($route, $owner), ['status' => 'approved'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $owner->fresh()->status);
        $this->assertDatabaseHas('sorting_centers', [
            'id' => $center->id, 'business_name' => 'Existing Company', 'approval_status' => 'approved',
            'id_doc_url' => 'private/original-id.pdf', 'permit_doc_url' => 'private/original-permit.pdf',
        ]);
    }

    public function test_manage_cannot_approve_an_unverified_onboarding_account(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $owner = User::factory()->unverified()->create(['usertype' => 'sorting_center', 'status' => 'unverified']);
        PartnerApplication::create([
            'user_id' => $owner->id, 'role' => 'sorting_center',
            'details' => ['registration_version' => 2, 'business_name' => 'Pending Company'],
            'address' => [], 'documents' => [], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.status', $owner), ['status' => 'approved'])
            ->assertStatus(409);

        $this->assertSame('unverified', $owner->fresh()->status);
        $this->assertDatabaseMissing('sorting_centers', ['user_id' => $owner->id]);
    }

    public function test_manage_can_reactivate_a_previously_approved_onboarding_account(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $owner = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'suspended']);
        SortingCenter::create([
            'user_id' => $owner->id, 'business_name' => 'Existing Company', 'approval_status' => 'approved',
        ]);
        PartnerApplication::create([
            'user_id' => $owner->id, 'role' => 'sorting_center',
            'details' => ['registration_version' => 2], 'address' => [], 'documents' => [],
            'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.status', $owner), ['status' => 'approved'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $owner->fresh()->status);
        $this->assertSame(1, SortingCenter::where('user_id', $owner->id)->count());
    }

    public function test_manage_keeps_rejection_in_sync_and_blocks_mobile_operations(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $owner = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create([
            'user_id' => $owner->id, 'business_name' => 'Existing Company', 'approval_status' => 'approved',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.status', $owner), [
            'status' => 'rejected', 'reason' => 'Review required',
        ])->assertRedirect();

        $this->assertSame('rejected', $owner->fresh()->status);
        $this->assertSame('rejected', $owner->fresh()->sortingCenter->approval_status);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner->fresh());
        $this->getJson('/api/sorting-center/parcels')->assertForbidden();
    }
}
