<?php

namespace Tests\Feature\Logistics;

use App\Models\PartnerApplication;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BatchOneAccessTest extends TestCase
{
    use RefreshDatabase;

    // LOG-RIDER-007: direct mutation attempts cannot change another company's Rider.
    public function test_foreign_rider_is_hidden_and_all_management_mutations_are_denied(): void
    {
        $center = $this->center();
        $other = $this->center();
        $rider = $this->rider($other);
        $before = $rider->fresh()->getAttributes();
        $userBefore = $rider->user->getAttributes();
        Sanctum::actingAs($center);

        $this->getJson('/api/sorting-center/riders')->assertOk()->assertJsonCount(0, 'data');
        foreach (['approve', 'reject', 'activate', 'deactivate'] as $action) {
            $this->postJson("/api/sorting-center/riders/{$rider->id}/{$action}")
                ->assertNotFound();
            $this->assertSame($before, $rider->fresh()->getAttributes());
            $this->assertSame($userBefore, $rider->user->fresh()->getAttributes());
        }
    }

    public static function documentReviewers(): array
    {
        return [
            'owning center' => ['owner', 200],
            'another company' => ['foreign', 403],
            'Buyer' => ['buyer', 403],
            'unapproved center' => ['pending', 403],
            'unavailable center user' => ['unavailable', 403],
            'approved Admin' => ['admin', 200],
            'document owner' => ['self', 200],
        ];
    }

    // LOG-RIDER-001 review subset / LOG-RIDER-007: real route, fake private files.
    #[DataProvider('documentReviewers')]
    public function test_document_review_keeps_existing_role_and_company_boundaries(string $actor, int $expected): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $center = $this->center();
        $rider = $this->rider($center);
        $documents = ['id_doc' => 'partner-documents/id.pdf', 'license_doc' => 'partner-documents/license.pdf', 'or_cr_doc' => 'partner-documents/or-cr.pdf'];
        PartnerApplication::create([
            'user_id' => $rider->user_id, 'role' => 'rider', 'details' => [], 'address' => [],
            'documents' => $documents, 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
        ]);
        foreach ($documents as $file) Storage::disk('local')->put($file, '%PDF-synthetic');

        $reviewer = match ($actor) {
            'owner' => $center,
            'foreign' => $this->center(),
            'pending' => tap($center, fn ($user) => $user->sortingCenter->update(['approval_status' => 'pending'])),
            'unavailable' => tap($center, fn ($user) => $user->update(['status' => 'rejected'])),
            'self' => $rider->user,
            default => User::factory()->create(['usertype' => $actor, 'status' => 'approved']),
        };

        foreach (array_keys($documents) as $key) {
            $response = $this->actingAs($reviewer)->get('/partner/documents/'.$rider->user_id.'/'.$key);
            $response->assertStatus($expected);
            if ($expected === 200) {
                $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $response->assertHeader('X-Content-Type-Options', 'nosniff');
            }
        }
        $this->assertSame($documents, $rider->user->fresh()->partnerApplication->documents);
        foreach ($documents as $file) $this->assertSame('%PDF-synthetic', Storage::disk('local')->get($file));
    }

    public function test_guest_api_and_document_requests_are_denied_and_missing_documents_are_not_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $center = $this->center();
        $rider = $this->rider($center);
        $url = '/partner/documents/'.$rider->user_id.'/license_doc';
        $this->getJson('/api/sorting-center/riders')->assertUnauthorized();
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($center)->get($url)->assertNotFound();
    }

    public function test_unapproved_and_wrong_role_accounts_cannot_list_riders(): void
    {
        $center = $this->center();
        $center->sortingCenter->update(['approval_status' => 'pending']);
        Sanctum::actingAs($center);
        $this->getJson('/api/sorting-center/riders')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']));
        $this->getJson('/api/sorting-center/riders')->assertForbidden();
    }

    public static function approvalFilters(): array
    {
        return [['pending'], ['approved'], ['rejected']];
    }

    // LOG-RIDER-001 review subset / LOG-RIDER-007: additive, private route metadata only.
    #[DataProvider('approvalFilters')]
    public function test_owned_review_list_filters_existing_states_without_exposing_application_paths(string $status): void
    {
        $center = $this->center();
        $records = [];
        foreach (['pending', 'approved', 'rejected'] as $state) {
            $rider = $this->rider($center);
            $rider->update(['approval_status' => $state]);
            PartnerApplication::create([
                'user_id' => $rider->user_id, 'role' => 'rider', 'details' => [], 'address' => [],
                'documents' => ['id_doc' => 'partner-documents/private-id.pdf', 'license_doc' => 'partner-documents/private-license.pdf'],
                'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
            ]);
            $records[$state] = $rider;
        }
        $this->rider($this->center())->update(['approval_status' => $status]);
        Sanctum::actingAs($center);
        $response = $this->getJson('/api/sorting-center/riders?status='.$status)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $records[$status]->id)
            ->assertJsonPath('data.0.approval_status', $status)
            ->assertJsonPath('data.0.review_documents.0.url', '/partner/documents/'.$records[$status]->user_id.'/id_doc')
            ->assertJsonPath('data.0.review_documents.1.available', true)
            ->assertJsonPath('data.0.review_documents.2.available', false)
            ->assertJsonPath('data.0.review_documents.2.url', null)
            ->assertJsonMissingPath('data.0.user.partner_application');
        $this->assertStringNotContainsString('partner-documents/private', $response->getContent());
    }

    public function test_bicycle_and_legacy_records_have_no_invented_motor_documents(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $rider->update(['vehicle_type' => 'bicycle']);
        Sanctum::actingAs($center);
        $this->getJson('/api/sorting-center/riders')->assertOk()
            ->assertJsonPath('data.0.vehicle_type', 'bicycle')
            ->assertJsonPath('data.0.review_documents.0.available', false)
            ->assertJsonPath('data.0.review_documents.1.available', false)
            ->assertJsonPath('data.0.review_documents.2.available', false);
        $rider->delete();
        $this->getJson('/api/sorting-center/riders?status=pending')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_read_only_rider_shell_reuses_existing_approval_and_session_guards(): void
    {
        $this->withoutVite();
        $center = $this->center();
        $this->getJson('/sorting-center/rider-management')->assertUnauthorized();
        $this->actingAs($center)->get('/sorting-center/rider-management')->assertOk()
            ->assertSee('<rider-management>', false)->assertSee('window.logisticsSession', false);
        $center->sortingCenter->update(['approval_status' => 'pending']);
        $center->unsetRelation('sortingCenter');
        $this->actingAs($center)->get('/sorting-center/rider-management')->assertRedirect(route('application.status'));
    }

    private function center(): User
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Synthetic Center '.$user->id, 'approval_status' => 'approved']);
        return $user->load('sortingCenter');
    }

    private function rider(User $center): Rider
    {
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'pending']);
        return Rider::create([
            'user_id' => $user->id, 'sorting_center_id' => $center->sortingCenter->id,
            'name' => 'Synthetic Rider', 'contact' => '09170000000', 'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-123', 'approval_status' => 'pending', 'is_active' => false,
        ]);
    }
}
