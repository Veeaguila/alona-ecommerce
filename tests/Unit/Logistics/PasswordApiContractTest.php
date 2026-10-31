<?php

namespace Tests\Unit\Logistics;

use App\Models\User;
use App\Models\SortingCenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PasswordApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->app->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Password contract tests require isolated test configuration.');
        }
        // These are mocked ownership/validation/response tests. No SQL is permitted.
        DB::connection()->beforeExecuting(static function () {
            throw new \LogicException('Password contract tests must not execute database queries.');
        });
    }

    private function operator(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 9001, 'usertype' => 'sorting_center',
            'status' => 'approved', 'password' => Hash::make('SyntheticOld#123')]);
        // Web shared props read these relationships even for a Logistics user.
        // Model them explicitly; real middleware and the no-SQL guard remain enabled.
        $center = new SortingCenter();
        $center->setRawAttributes(['id' => 7001, 'user_id' => 9001, 'approval_status' => 'approved']);
        $user->setRelation('sortingCenter', $center);
        $application = Mockery::mock(HasOne::class);
        $application->shouldReceive('exists')->andReturn(false);
        $user->shouldReceive('partnerApplication')->andReturn($application);
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

    private function input(): array
    {
        return ['current_password' => 'SyntheticOld#123', 'password' => 'SyntheticNew#456',
            'password_confirmation' => 'SyntheticNew#456'];
    }

    public function test_api_requires_authentication(): void
    {
        $this->putJson('/api/partner/password', $this->input())->assertUnauthorized();
    }

    public function test_api_updates_only_authenticated_users_password_and_returns_no_secrets(): void
    {
        $user = $this->operator();
        $user->shouldReceive('update')->once()->withArgs(fn ($attributes) =>
            array_keys($attributes) === ['password'] && Hash::check('SyntheticNew#456', $attributes['password']))->andReturn(true);
        Sanctum::actingAs($user);
        $this->putJson('/api/partner/password', $this->input())->assertOk()
            ->assertExactJson(['message' => 'Password updated.']);
    }

    public function test_wrong_current_password_does_not_write(): void
    {
        $user = $this->operator();
        $user->shouldNotReceive('update');
        Sanctum::actingAs($user);
        $this->putJson('/api/partner/password', array_merge($this->input(), ['current_password' => 'Wrong#123']))
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_policy_and_confirmation_are_identical_to_web_validation(): void
    {
        $user = $this->operator();
        $user->shouldNotReceive('update');
        Sanctum::actingAs($user);
        $this->putJson('/api/partner/password', array_merge($this->input(), ['password_confirmation' => 'Mismatch#789']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/partner/password', array_merge($this->input(), ['password' => 'weak', 'password_confirmation' => 'weak']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_web_keeps_its_existing_redirect_response(): void
    {
        $user = $this->operator();
        $user->shouldReceive('update')->once()->withArgs(fn ($attributes) =>
            Hash::check('SyntheticNew#456', $attributes['password']))->andReturn(true);
        $this->actingAs($user, 'web')->from('/profile')->put('/password', $this->input())->assertRedirect('/profile');
    }
}
