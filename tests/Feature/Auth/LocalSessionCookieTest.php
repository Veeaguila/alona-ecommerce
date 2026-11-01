<?php

namespace Tests\Feature\Auth;

use App\Models\{SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalSessionCookieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['session.domain' => '.shopalona.online', 'session.secure' => true, 'session.same_site' => 'lax']);
    }

    public static function origins(): array
    {
        return [
            'direct local IP' => ['local', 'http://127.0.0.1', '127.0.0.1', [], true],
            'direct localhost' => ['local', 'http://localhost', '::1', [], true],
            'production loopback' => ['production', 'http://127.0.0.1', '127.0.0.1', [], false],
            'remote client' => ['local', 'http://127.0.0.1', '198.51.100.1', [], false],
            'hosted domain through local tunnel' => ['local', 'https://shopalona.online', '127.0.0.1', [], false],
            'forwarded localhost' => ['local', 'http://localhost', '127.0.0.1', ['X-Forwarded-For' => '198.51.100.1'], false],
            'forwarded host' => ['local', 'http://localhost', '127.0.0.1', ['X-Forwarded-Host' => 'shopalona.online'], false],
            'standard forwarded header' => ['local', 'http://localhost', '127.0.0.1', ['Forwarded' => 'for=198.51.100.1;proto=https'], false],
        ];
    }

    #[DataProvider('origins')]
    public function test_cookie_override_only_applies_to_direct_local_requests(string $environment, string $origin, string $remote, array $headers, bool $local): void
    {
        $this->app->instance('env', $environment);
        $response = $this->withServerVariables(['REMOTE_ADDR' => $remote])->get($origin.'/login', $headers);
        $response->assertRedirect();
        $cookies = $response->headers->getCookies();
        $this->assertGreaterThanOrEqual(2, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertSame($local ? '' : '.shopalona.online', $cookie->getDomain() ?? '');
            $this->assertSame(!$local, $cookie->isSecure());
            $this->assertSame('lax', $cookie->getSameSite());
        }
        // A request must not change the configured defaults for a subsequent hosted request.
        $this->assertSame('.shopalona.online', config('session.domain'));
        $this->assertTrue(config('session.secure'));
        $cookie = app('cookie')->make('probe', 'test');
        $this->assertSame('.shopalona.online', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
    }

    public function test_local_logistics_login_keeps_the_role_redirect_and_can_open_the_dashboard(): void
    {
        $this->app->instance('env', 'local');
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Synthetic Local Center', 'approval_status' => 'approved']);
        $this->withSession(['_token' => 'local-login-test-csrf-token']);
        $response = $this->post('http://127.0.0.1/login', [
            'email' => $user->email, 'password' => 'password', '_token' => 'local-login-test-csrf-token',
        ], ['X-Inertia' => 'true']);
        $response->assertStatus(409)->assertHeader('X-Inertia-Location', 'http://127.0.0.1/sorting-center/dashboard');
        $this->assertAuthenticatedAs($user);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertEmpty($cookie->getDomain());
            $this->assertFalse($cookie->isSecure());
        }
        $this->get('http://127.0.0.1/sorting-center/dashboard')->assertOk()->assertSee('window.logisticsSession', false);
    }
}
