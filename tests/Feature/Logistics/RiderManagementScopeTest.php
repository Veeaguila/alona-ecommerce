<?php

namespace Tests\Feature\Logistics;

use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderManagementScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_centers_only_see_and_approve_their_own_rider_applications(): void
    {
        $firstCenter = $this->sortingCenter('First Center');
        $secondCenter = $this->sortingCenter('Second Center');
        $firstRider = $this->rider($firstCenter->sortingCenter->id, 'first-rider@example.test');
        $secondRider = $this->rider($secondCenter->sortingCenter->id, 'second-rider@example.test');

        Sanctum::actingAs($firstCenter);

        $this->getJson('/api/sorting-center/riders?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstRider->id);

        $this->postJson("/api/sorting-center/riders/{$secondRider->id}/approve")
            ->assertNotFound();

        $this->assertDatabaseHas('riders', [
            'id' => $secondRider->id,
            'approval_status' => 'pending',
        ]);

        $this->postJson("/api/sorting-center/riders/{$firstRider->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved');
    }

    public function test_browser_riders_link_opens_the_rider_management_page(): void
    {
        $this->withoutVite();
        $this->actingAs($this->sortingCenter('Browser Center'));

        $this->get('/sorting-center/riders')
            ->assertRedirect(route('sorting-center.rider-management'));

        $this->get('/sorting-center/rider-management')
            ->assertOk()
            ->assertViewIs('sorting-center.rider-management')
            ->assertSee('<rider-management>', false);
    }

    public function test_inertia_riders_navigation_redirects_to_the_page_instead_of_json(): void
    {
        $this->actingAs($this->sortingCenter('Inertia Center'));

        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)
            ->version(\Illuminate\Http\Request::create('/sorting-center/riders'));

        $this->get('/sorting-center/riders', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('sorting-center.rider-management'));
    }

    public function test_web_and_api_json_lists_keep_status_filter_and_company_scope(): void
    {
        $center = $this->sortingCenter('JSON Center');
        $foreignCenter = $this->sortingCenter('Foreign Center');
        $pending = $this->rider($center->sortingCenter->id, 'pending-json@example.test');
        $approved = $this->rider($center->sortingCenter->id, 'approved-json@example.test');
        $approved->update(['approval_status' => 'approved']);
        $this->rider($foreignCenter->sortingCenter->id, 'foreign-json@example.test');
        $this->actingAs($center);

        foreach (['/sorting-center/riders', '/api/sorting-center/riders'] as $url) {
            $this->getJson($url.'?status=pending')
                ->assertOk()
                ->assertJsonPath('message', 'Riders retrieved successfully.')
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $pending->id);
        }
    }

    public function test_public_rider_registration_directory_only_lists_approved_centers(): void
    {
        $approvedCenter = $this->sortingCenter('Approved Center', 'approved');
        $this->sortingCenter('Pending Center', 'pending');

        $this->getJson('/api/sorting-centers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approvedCenter->sortingCenter->id)
            ->assertJsonPath('data.0.business_name', 'Approved Center');
    }

    public function test_rider_registration_rejects_unapproved_sorting_centers(): void
    {
        $pendingCenter = $this->sortingCenter('Pending Center', 'pending');

        $this->postJson('/api/rider/register', [
            'sorting_center_id' => $pendingCenter->sortingCenter->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sorting_center_id');
    }

    private function sortingCenter(string $name, string $status = 'approved'): User
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        SortingCenter::create([
            'user_id' => $user->id,
            'business_name' => $name,
            'approval_status' => $status,
        ]);

        return $user->load('sortingCenter');
    }

    private function rider(int $sortingCenterId, string $email): Rider
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'rider', 'email' => $email]);

        return Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $sortingCenterId,
            'name' => 'Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-'.substr(md5($email), 0, 5),
            'approval_status' => 'pending',
            'is_active' => false,
        ]);
    }
}
