<?php

namespace Tests\Unit\Logistics;

use App\Http\Controllers\Logistics\RiderManagementController;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use App\Notifications\RiderActivationNotification;
use App\Services\RiderWorkload;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RiderActivationTokenExposureTest extends TestCase
{
    private ConnectionResolverInterface $originalResolver;
    private Connection $connection;
    private array $row;
    private User $riderUser;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->originalResolver = Model::getConnectionResolver();

        // No PDO is opened. Unexpected database execution fails closed.
        $this->connection = Mockery::mock(Connection::class.'[select,update]', [
            static function () {
                throw new \LogicException('This regression test must not connect to a database.');
            },
            ':memory:',
            '',
            ['name' => 'h1_synthetic'],
        ]);
        $resolver = new ConnectionResolver(['h1_synthetic' => $this->connection]);
        $resolver->setDefaultConnection('h1_synthetic');
        Model::setConnectionResolver($resolver);

        $this->row = [
            'id' => 91,
            'sorting_center_id' => 7,
            'user_id' => 22,
            'name' => 'Synthetic Rider',
            'approval_status' => 'pending_activation',
            'is_active' => false,
            'activation_token' => 'synthetic-invitation-token',
            'activation_token_expires_at' => now()->addHours(24)->format('Y-m-d H:i:s'),
        ];
        $this->riderUser = new User();
        $this->riderUser->setRawAttributes([
            'id' => 22,
            'name' => 'Synthetic Rider',
            'first_name' => 'Synthetic',
            'email' => 'rider@example.test',
            'usertype' => 'rider',
            'status' => 'pending_activation',
        ], true);
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver($this->originalResolver);

        parent::tearDown();
    }

    public static function serializationShapes(): array
    {
        return [
            'array' => ['array'],
            'json' => ['json'],
            'collection' => ['collection'],
            'nested relationship' => ['relationship'],
        ];
    }

    #[DataProvider('serializationShapes')]
    public function test_activation_token_is_absent_from_serialized_riders(string $shape): void
    {
        $rider = new Rider();
        $rider->setRawAttributes($this->row, true);
        $payload = match ($shape) {
            'array' => $rider->toArray(),
            'json' => json_decode($rider->toJson(), true, 512, JSON_THROW_ON_ERROR),
            'collection' => (new Collection([$rider]))->toArray()[0],
            'relationship' => (new User())->setRelation('rider', $rider)->toArray()['rider'],
        };

        $this->assertArrayNotHasKey('activation_token', $payload);
        $this->assertStringNotContainsString($this->row['activation_token'], json_encode($payload));
        $this->assertSame(91, $payload['id']);
        $this->assertSame('pending_activation', $payload['approval_status']);
    }

    public function test_hiding_does_not_remove_the_internal_token_or_email_activation_link(): void
    {
        $rider = new Rider();
        $rider->setRawAttributes($this->row, true);
        $this->assertSame($this->row['activation_token'], $rider->activation_token);
        $this->assertTrue($rider->activation_token_expires_at->isFuture());

        $notification = new RiderActivationNotification($rider->activation_token, 'Synthetic Logistics');
        $this->assertSame(['mail'], $notification->via($this->riderUser));
        $this->assertStringEndsWith(
            '/rider/activate/'.$rider->activation_token,
            $notification->toMail($this->riderUser)->actionUrl,
        );
    }

    public function test_rider_listing_does_not_expose_tokens(): void
    {
        $this->syntheticReads();
        $this->mock(RiderWorkload::class)->shouldReceive('append')->once();

        $response = (new RiderManagementController())->index($this->request('GET'));
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(91, $data['data'][0]['id']);
        $this->assertArrayNotHasKey('activation_token', $data['data'][0]);
        $this->assertStringNotContainsString($this->row['activation_token'], $response->getContent());
        Notification::assertNothingSent();
    }

    public static function invitationStates(): array
    {
        return ['unexpired invitation' => [false], 'expired invitation' => [true]];
    }

    #[DataProvider('invitationStates')]
    public function test_resend_delivers_new_token_only_through_email(bool $expired): void
    {
        if ($expired) {
            $this->row['activation_token_expires_at'] = now()->subMinute()->format('Y-m-d H:i:s');
        }
        $this->syntheticReads();
        $written = [];
        $this->connection->shouldReceive('update')->once()
            ->andReturnUsing(function ($sql, $bindings) use (&$written) {
                $this->assertStringContainsString('"riders"', $sql);
                $this->assertSame(91, end($bindings));
                $written = $this->connection->prepareBindings($bindings);

                return 1;
            });

        $startedAt = now()->startOfSecond();
        $response = (new RiderManagementController())->resendActivation($this->request(), 91);
        $data = $response->getData(true);
        $notification = Notification::sent($this->riderUser, RiderActivationNotification::class)->sole();

        Notification::assertSentToTimes($this->riderUser, RiderActivationNotification::class, 1);
        $this->assertNotSame($this->row['activation_token'], $notification->token);
        $this->assertSame(40, strlen($notification->token));
        $this->assertContains($notification->token, $written);
        $deadline = \Illuminate\Support\Carbon::parse($data['data']['activation_token_expires_at']);
        // Eloquent omits an unchanged expiry from UPDATE bindings.
        $this->assertContains($deadline->format('Y-m-d H:i:s'), [...$written, $this->row['activation_token_expires_at']]);
        $this->assertGreaterThanOrEqual($startedAt->copy()->addHours(24)->timestamp, $deadline->timestamp);
        $this->assertLessThanOrEqual(now()->addHours(24)->timestamp, $deadline->timestamp);
        $this->assertStringEndsWith(
            '/rider/activate/'.$notification->token,
            $notification->toMail($this->riderUser)->actionUrl,
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Activation email resent successfully.', $data['message']);
        $this->assertSame(91, $data['data']['rider_id']);
        $this->assertArrayNotHasKey('activation_token', $data['data']);
        $this->assertStringNotContainsString($notification->token, $response->getContent());
        $this->assertStringNotContainsString($this->row['activation_token'], $response->getContent());
    }

    public function test_resend_keeps_the_organization_scope(): void
    {
        $this->syntheticReads(false);
        $this->connection->shouldNotReceive('update');

        $response = (new RiderManagementController())->resendActivation($this->request(), 91);

        $this->assertSame(404, $response->getStatusCode());
        Notification::assertNothingSent();
    }

    public function test_already_activated_rider_cannot_receive_another_invitation(): void
    {
        $this->row['approval_status'] = 'approved';
        $this->row['activation_token'] = null;
        $this->syntheticReads();
        $this->connection->shouldNotReceive('update');

        $response = (new RiderManagementController())->resendActivation($this->request(), 91);

        $this->assertSame(422, $response->getStatusCode());
        Notification::assertNothingSent();
    }

    private function syntheticReads(bool $ownedRiderExists = true): void
    {
        $this->connection->shouldReceive('select')->andReturnUsing(
            function ($sql, $bindings) use ($ownedRiderExists) {
                if (str_contains($sql, 'from "riders"')) {
                    $this->assertStringContainsString('"sorting_center_id" = ?', $sql);
                    $this->assertContains(7, $bindings);

                    return $ownedRiderExists ? [(object) $this->row] : [];
                }
                if (str_contains($sql, 'from "users"')) {
                    return [(object) $this->riderUser->getAttributes()];
                }
                if (str_contains($sql, 'from "partner_applications"')) {
                    return [];
                }

                throw new \LogicException('Unexpected query in synthetic H1 test.');
            },
        );
    }

    private function request(string $method = 'POST'): Request
    {
        $operator = new User();
        $operator->setRelation('sortingCenter', new SortingCenter([
            'business_name' => 'Synthetic Logistics',
        ]));
        $operator->sortingCenter->id = 7;
        $request = Request::create('/api/sorting-center/riders', $method);
        $request->setUserResolver(fn () => $operator);

        return $request;
    }
}
