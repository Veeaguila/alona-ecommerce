<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function google(string $email, string $id = 'google-123'): void
    {
        $identity = (new GoogleUser)->map(['id' => $id, 'name' => 'Google Applicant', 'email' => $email]);
        $identity->user = ['email_verified' => true];
        $driver = Mockery::mock();
        $driver->shouldReceive('setHttpClient')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($identity);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_new_google_partner_completes_form_before_an_account_is_created(): void
    {
        $this->google('new@example.test');
        $this->withSession(['google.signup_role' => 'sorting_center'])->get('/auth/google/callback')
            ->assertRedirect(route('register.logistics'))->assertSessionHas('onboarding.google.role', 'sorting_center');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_existing_google_buyer_keeps_their_role_despite_rider_selection(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test', 'usertype' => 'buyer', 'status' => 'approved']);
        $this->google($user->email);
        $this->withSession(['google.signup_role' => 'rider', 'url.intended' => '/admin'])->get('/auth/google/callback')
            ->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('buyer', $user->fresh()->usertype);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_pending_google_partner_is_sent_to_status(): void
    {
        $user = User::factory()->create(['email' => 'pending@example.test', 'usertype' => 'sorting_center', 'status' => 'pending']);
        $this->google($user->email);
        $this->get('/auth/google/callback')->assertRedirect(route('application.status'));
    }

    public function test_google_identity_collision_does_not_relink_accounts(): void
    {
        $first = User::factory()->create(['email' => 'first@example.test', 'google_id' => 'google-123']);
        $second = User::factory()->create(['email' => 'second@example.test']);
        $this->google($second->email);
        $this->get('/auth/google/callback')->assertRedirect('/?auth=login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($second->fresh()->google_id);
        $this->assertSame('google-123', $first->fresh()->google_id);
    }

    public function test_google_sign_in_preserves_an_uploaded_rider_photo(): void
    {
        $user = User::factory()->create(['email' => 'rider-photo@example.test', 'usertype' => 'rider', 'status' => 'pending']);
        $path = 'rider-avatars/'.$user->id.'/selected.png';
        $user->update(['avatar' => $path]);
        $this->google($user->email);
        $this->get('/auth/google/callback')->assertRedirect(route('application.status'));
        $this->assertSame($path, $user->fresh()->avatar);
        $this->assertAuthenticatedAs($user);
    }
}
