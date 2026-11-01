<?php
namespace Tests\Unit\Logistics;

use App\Models\{PartnerApplication, Rider, SortingCenter, User};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne};
use Illuminate\Support\Facades\{DB, Route, Storage};
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class RiderDocumentApiContractTest extends TestCase
{
    private User $target;
    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->app->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Document contracts require isolation.');
        }
        DB::connection()->beforeExecuting(static function () { throw new \LogicException('Document contracts forbid SQL.'); });
        // Membership lookup belongs to the new authorization boundary; this fixture
        // supplies an existing company owner while keeping document contracts SQL-free.
        $hubAccess = Mockery::mock(\App\Services\HubAccess::class)->makePartial();
        $hubAccess->shouldReceive('membership')->andReturnNull();
        $this->app->instance(\App\Services\HubAccess::class, $hubAccess);
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('synthetic-documents/id.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4/QAAAAASUVORK5CYII='));
        $this->target = new User();
        $this->target->setRawAttributes(['id' => 777, 'usertype' => 'rider']);
        $rider = new Rider();
        $rider->setRawAttributes(['id' => 34, 'user_id' => 777, 'sorting_center_id' => 7001]);
        $this->target->setRelation('rider', $rider);
        $application = new PartnerApplication();
        $application->setRawAttributes(['documents' => json_encode(['id_doc' => 'synthetic-documents/id.png'])]);
        $this->target->setRelation('partnerApplication', $application);
        // Explicit model binding supplies only isolated objects; all controller and
        // Sorting Center authorization middleware still execute, with SQL forbidden.
        Route::bind('user', function ($id) {
            abort_unless((int) $id === 777, 404);
            return $this->target;
        });
    }
    private function operator(string $role = 'sorting_center', string $status = 'approved'): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 9001, 'usertype' => $role, 'status' => $status, 'email_verified_at' => '2026-10-25 00:00:00']);
        $user->exists = true;
        $center = new SortingCenter();
        $center->setRawAttributes(['id' => 7001, 'approval_status' => 'approved']);
        $user->setRelation('sortingCenter', $center);
        $user->setRelation('partnerApplication', null);
        $relation = Mockery::mock(HasOne::class);
        $relation->shouldReceive('exists')->andReturn(false);
        $user->shouldReceive('partnerApplication')->andReturn($relation);
        $cart = Mockery::mock(HasMany::class);
        $cart->shouldReceive('sum')->with('quantity')->andReturn(0);
        $user->shouldReceive('cartItems')->andReturn($cart);
        $notifications = Mockery::mock(HasMany::class);
        $notifications->shouldReceive('whereNull')->with('read_at')->andReturnSelf();
        $notifications->shouldReceive('count')->andReturn(0);
        $notifications->shouldReceive('latest')->andReturnSelf();
        $notifications->shouldReceive('take')->with(5)->andReturnSelf();
        $notifications->shouldReceive('get')->with(['id', 'user_id', 'title', 'message', 'read_at', 'created_at'])->andReturn(new Collection());
        $user->shouldReceive('buyerNotifications')->andReturn($notifications);
        return $user;
    }
    public function test_unauthenticated_read_is_denied(): void
    {
        $this->getJson('/api/sorting-center/rider-documents/777/id_doc')->assertUnauthorized();
    }
    public function test_owned_rider_document_is_private_no_store_and_uses_user_not_rider_id(): void
    {
        Sanctum::actingAs($this->operator());
        $response = $this->getJson('/api/sorting-center/rider-documents/777/id_doc')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson('/api/sorting-center/rider-documents/34/id_doc')->assertNotFound();
    }
    public function test_other_company_rider_document_is_forbidden(): void
    {
        $this->target->rider->sorting_center_id = 7002;
        Sanctum::actingAs($this->operator());
        $this->getJson('/api/sorting-center/rider-documents/777/id_doc')->assertForbidden();
    }
    public function test_non_logistics_roles_cannot_use_the_native_logistics_alias(): void
    {
        foreach (['rider', 'admin', 'buyer'] as $role) {
            Sanctum::actingAs($this->operator(role: $role));
            $this->getJson('/api/sorting-center/rider-documents/777/id_doc')->assertForbidden();
        }
    }
    public function test_unapproved_operator_cannot_read_documents(): void
    {
        Sanctum::actingAs($this->operator(status: 'pending'));
        $this->getJson('/api/sorting-center/rider-documents/777/id_doc')->assertForbidden();
    }
    public function test_only_supported_document_keys_can_be_requested(): void
    {
        Sanctum::actingAs($this->operator());
        $this->getJson('/api/sorting-center/rider-documents/777/unknown_key')->assertNotFound();
    }
    public function test_missing_document_returns_not_found_instead_of_a_storage_path(): void
    {
        Sanctum::actingAs($this->operator());
        $this->getJson('/api/sorting-center/rider-documents/777/license_doc')->assertNotFound();
    }
    public function test_existing_web_document_route_is_unchanged(): void
    {
        $this->actingAs($this->operator(), 'web')->get('/partner/documents/777/id_doc')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
