<?php

namespace Tests\Unit\Logistics;

use App\Models\PartnerApplication;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class VerificationEmailApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->app->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Verification contracts require isolated configuration.');
        }
        DB::connection()->beforeExecuting(static function () {
            throw new \LogicException('Verification contracts must not execute SQL.');
        });
    }

    private function operator(bool $v2 = false, bool $verified = false, string $role = 'sorting_center'): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 9001, 'name' => 'Synthetic operator',
            'email' => 'synthetic@example.test', 'usertype' => $role, 'status' => 'approved',
            'email_verified_at' => $verified ? '2026-10-25 00:00:00' : null]);
        $center = new SortingCenter();
        $center->setRawAttributes(['id' => 7001, 'user_id' => 9001, 'approval_status' => 'approved']);
        $user->setRelation('sortingCenter', $center);
        $application = null;
        if ($v2) {
            $application = new PartnerApplication();
            $application->setRawAttributes(['details' => json_encode(['registration_version' => 2])]);
        }
        $user->setRelation('partnerApplication', $application);
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

    public function test_authentication_is_required(): void
    {
        $this->postJson('/api/sorting-center/onboarding/resend-link')->assertUnauthorized();
    }

    public function test_only_authenticated_owner_receives_mail_and_response_contains_no_link_or_secrets(): void
    {
        $user = $this->operator();
        $user->shouldReceive('sendEmailVerificationNotification')->once();
        Sanctum::actingAs($user);
        $this->postJson('/api/sorting-center/onboarding/resend-link', ['user_id' => 9999, 'email' => 'other@example.test'])
            ->assertOk()->assertExactJson(['message' => 'Verification email sent.']);
    }

    public function test_other_roles_cannot_use_the_logistics_adapter(): void
    {
        foreach (['rider', 'admin', 'buyer'] as $role) {
            $user = $this->operator(role: $role);
            $user->shouldNotReceive('sendEmailVerificationNotification');
            Sanctum::actingAs($user);
            $this->postJson('/api/sorting-center/onboarding/resend-link')->assertForbidden();
        }
    }

    public function test_v2_accounts_keep_the_otp_flow(): void
    {
        $user = $this->operator(v2: true);
        $user->shouldNotReceive('sendEmailVerificationNotification');
        Sanctum::actingAs($user);
        $this->postJson('/api/sorting-center/onboarding/resend-link')->assertUnprocessable()
            ->assertExactJson(['message' => 'Use Resend code to request a new six-digit code.']);
    }

    public function test_verified_accounts_do_not_receive_duplicate_mail(): void
    {
        $user = $this->operator(verified: true);
        $user->shouldNotReceive('sendEmailVerificationNotification');
        Sanctum::actingAs($user);
        $this->postJson('/api/sorting-center/onboarding/resend-link')->assertOk()
            ->assertExactJson(['message' => 'Email already verified.']);
    }

    public function test_mail_failure_is_safe_and_not_reported_as_sent(): void
    {
        $user = $this->operator();
        $user->shouldReceive('sendEmailVerificationNotification')->once()->andThrow(new \RuntimeException('Synthetic private transport detail'));
        Sanctum::actingAs($user);
        $this->postJson('/api/sorting-center/onboarding/resend-link')->assertStatus(503)
            ->assertExactJson(['message' => 'The verification email could not be sent. Please try again later.']);
    }

    public function test_existing_api_throttle_is_enforced(): void
    {
        $user = $this->operator();
        $user->shouldReceive('sendEmailVerificationNotification')->times(6);
        Sanctum::actingAs($user);
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/sorting-center/onboarding/resend-link')->assertOk();
        }
        $this->postJson('/api/sorting-center/onboarding/resend-link')->assertTooManyRequests();
    }

    public function test_existing_web_redirect_and_flash_are_preserved(): void
    {
        $user = $this->operator();
        $user->shouldReceive('sendEmailVerificationNotification')->once();
        $this->actingAs($user, 'web')->from('/application/status')->post('/email/verification-notification')
            ->assertRedirect('/application/status')->assertSessionHas('status', 'verification-link-sent');
    }

    public function test_existing_web_v2_uses_otp_error_redirect(): void
    {
        $user = $this->operator(v2: true);
        $user->shouldNotReceive('sendEmailVerificationNotification');
        $this->actingAs($user, 'web')->post('/email/verification-notification')
            ->assertRedirect(route('application.status'))->assertSessionHasErrors('otp');
    }
}
