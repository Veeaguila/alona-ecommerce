<?php

namespace Tests\Feature\Logistics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_rider_token_and_repeat_is_unauthorized(): void
    {
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'pending']);
        $current = $user->createToken('current');
        $other = $user->createToken('other');
        $this->withToken($current->plainTextToken)->postJson('/api/logistics/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($current->plainTextToken)->postJson('/api/logistics/logout')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other->plainTextToken)->postJson('/api/logistics/logout')->assertOk();
    }

    public function test_invalid_tokens_cannot_read_profile_or_logout(): void
    {
        $this->withToken('invalid')->getJson('/api/partner/profile')->assertUnauthorized();
        $this->withToken('invalid')->postJson('/api/logistics/logout')->assertUnauthorized();
    }

    public function test_missing_rider_profile_fails_closed(): void
    {
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/partner/profile')->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/rider/deliveries')->assertForbidden();
    }

    public function test_expired_token_is_unauthorized_on_logout(): void
    {
        $user = User::factory()->create(['usertype' => 'rider']);
        $expired = $user->createToken('expired', ['*'], now()->subMinute());
        $this->withToken($expired->plainTextToken)->postJson('/api/logistics/logout')->assertUnauthorized();
    }

    public function test_canonical_registration_email_and_exact_legacy_email_can_log_in(): void
    {
        $canonical = User::factory()->create(['usertype' => 'rider', 'status' => 'approved', 'email' => 'rider@example.test']);
        $legacy = User::factory()->create(['usertype' => 'rider', 'status' => 'approved', 'email' => 'Legacy@example.test']);
        $this->postJson('/api/rider/login', ['email' => 'RIDER@EXAMPLE.TEST', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.id', $canonical->id);
        $this->postJson('/api/rider/login', ['email' => 'Legacy@example.test', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.id', $legacy->id);
    }
}
