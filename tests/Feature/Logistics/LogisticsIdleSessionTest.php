<?php

namespace Tests\Feature\Logistics;

use App\Models\{LogisticsHub, LogisticsHubStaff, SortingCenter, User};
use App\Services\LogisticsSessionLifetime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogisticsIdleSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->freezeTime();
    }

    private function owner(): User
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Synthetic Idle Company', 'approval_status' => 'approved']);
        return $user;
    }

    private function api(string $token, string $url = '/api/user')
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($token)->getJson($url);
    }

    public function test_unused_logistics_token_expires_at_exactly_thirty_minutes_and_cannot_be_revived(): void
    {
        $token = $this->owner()->createToken('sorting-center-token');
        $this->travel(1800)->seconds();
        $this->api($token->plainTextToken)->assertUnauthorized();
        $this->assertNull($token->accessToken->fresh()->last_used_at);
        $this->travel(1)->seconds();
        $this->api($token->plainTextToken, '/api/sorting-center/dashboard')->assertUnauthorized();
    }

    public function test_authenticated_requests_extend_the_idle_window_but_not_other_sessions(): void
    {
        $owner = $this->owner();
        $active = $owner->createToken('active');
        $idle = $owner->createToken('idle');
        $this->travel(1799)->seconds();
        $this->api($active->plainTextToken)->assertOk();
        $this->travel(1799)->seconds();
        $this->api($active->plainTextToken)->assertOk();
        $this->api($idle->plainTextToken)->assertUnauthorized();
        $this->travel(1800)->seconds();
        $this->api($active->plainTextToken)->assertUnauthorized();
    }

    public function test_real_password_login_issues_a_token_subject_to_idle_expiry(): void
    {
        $owner = $this->owner();
        $plain = $this->postJson('/api/sorting-center/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertOk()->json('token');
        $this->travel(1800)->seconds();
        $this->api($plain)->assertUnauthorized();
    }

    public function test_old_existing_tokens_and_explicit_hard_expiry_are_rejected_without_changing_other_role_tokens(): void
    {
        $owner = $this->owner();
        $legacy = $owner->createToken('legacy');
        $legacy->accessToken->forceFill(['created_at' => now()->subYear()])->save();
        $this->api($legacy->plainTextToken)->assertUnauthorized();
        $hard = $owner->createToken('hard', ['*'], now()->subSecond());
        $this->api($hard->plainTextToken)->assertUnauthorized();
        foreach (['rider', 'buyer', 'seller', 'admin'] as $role) {
            $user = User::factory()->create(['usertype' => $role]);
            $token = $user->createToken('existing-other-role');
            $token->accessToken->forceFill(['created_at' => now()->subYear()])->save();
            $this->api($token->plainTextToken)->assertOk()->assertJsonPath('usertype', $role);
        }
    }

    public function test_hub_staff_tokens_expire_and_fresh_login_retains_only_assigned_hub_access(): void
    {
        $owner = $this->owner();
        $hub = LogisticsHub::create(['sorting_center_id' => $owner->sortingCenter->id, 'name' => 'Synthetic hub', 'code' => 'IDLE',
            'address' => 'Synthetic address', 'city_code' => '1381300000', 'pickup_city_codes' => [], 'delivery_city_codes' => [], 'is_active' => true]);
        $staff = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        LogisticsHubStaff::create(['user_id' => $staff->id, 'sorting_center_id' => $owner->sortingCenter->id, 'logistics_hub_id' => $hub->id, 'is_active' => true]);
        $old = $staff->createToken('staff');
        $this->travel(1800)->seconds();
        $this->api($old->plainTextToken, '/api/sorting-center/hub-workspace')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $plain = $this->postJson('/api/sorting-center/login', ['email' => $staff->email, 'password' => 'password'])->assertOk()->json('token');
        $this->api($plain, '/api/sorting-center/hub-workspace')->assertOk()->assertJsonPath('data.operator.role', 'hub_staff');
        $this->api($plain, '/api/sorting-center/dashboard')->assertForbidden();
    }

    public function test_idle_browser_session_is_invalidated_and_only_its_bound_token_is_revoked(): void
    {
        $owner = $this->owner();
        $bound = $owner->createToken('logistics-web');
        $other = $owner->createToken('other-device');
        $bound->accessToken->forceFill(['created_at' => now()->subHour(), 'last_used_at' => now()->subMinutes(30)])->save();
        $this->actingAs($owner)->withSession([LogisticsSessionLifetime::ACTIVITY_KEY => now()->subMinutes(30)->timestamp,
            'logistics.web_token' => $bound->plainTextToken])->getJson('/sorting-center/dashboard')->assertUnauthorized();
        $this->assertGuest('web');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $bound->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertNull(session('logistics.web_token'));
    }

    public function test_idle_browser_html_redirects_to_login_and_cannot_reissue_a_token(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->withSession([LogisticsSessionLifetime::ACTIVITY_KEY => now()->subMinutes(30)->timestamp])
            ->get('/sorting-center/dashboard')->assertRedirect('/?auth=login');
        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_browser_api_activity_keeps_navigation_active_and_idle_tokens_rotate_for_active_web_sessions(): void
    {
        $owner = $this->owner();
        $bound = $owner->createToken('logistics-web');
        $bound->accessToken->forceFill(['created_at' => now()->subHour(), 'last_used_at' => now()->subMinutes(10)])->save();
        $this->actingAs($owner)->withSession([LogisticsSessionLifetime::ACTIVITY_KEY => now()->subHour()->timestamp,
            'logistics.web_token' => $bound->plainTextToken])->get('/sorting-center/dashboard')->assertOk();
        $this->assertSame($bound->plainTextToken, session('logistics.web_token'));
        $bound->accessToken->forceFill(['last_used_at' => now()->subMinutes(30)])->save();
        $this->get('/sorting-center/dashboard')->assertOk();
        $this->assertNotSame($bound->plainTextToken, session('logistics.web_token'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $bound->accessToken->id]);
    }

    public function test_web_login_initializes_activity_and_buyer_sessions_keep_their_existing_lifetime(): void
    {
        $owner = $this->owner();
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect();
        $this->assertSame(now()->timestamp, session(LogisticsSessionLifetime::ACTIVITY_KEY));
        $this->assertAuthenticatedAs($owner);
        $buyer = User::factory()->create(['usertype' => 'buyer']);
        $this->actingAs($buyer)->withSession([LogisticsSessionLifetime::ACTIVITY_KEY => now()->subHour()->timestamp])->get('/')->assertRedirect();
        $this->assertAuthenticatedAs($buyer);
    }
}
