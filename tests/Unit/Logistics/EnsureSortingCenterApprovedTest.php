<?php

namespace Tests\Unit\Logistics;

use App\Http\Middleware\EnsureSortingCenterApproved;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureSortingCenterApprovedTest extends TestCase
{
    private EnsureSortingCenterApproved $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureSortingCenterApproved();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthenticated.', $data['message']);
    }

    public function test_non_sorting_center_user_is_forbidden(): void
    {
        $user = new User();
        $user->usertype = 'customer';

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden. This action is restricted to sorting centers.', $data['message']);
    }

    public function test_pending_sorting_center_is_forbidden(): void
    {
        $user = new User();
        $user->usertype = 'sorting_center';
        $user->status = 'approved';

        $sortingCenter = new SortingCenter();
        $sortingCenter->approval_status = 'pending';
        $user->setRelation('sortingCenter', $sortingCenter);

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('pending', $data['approval_status']);
        $this->assertEquals('Your sorting center account is still awaiting administrator approval.', $data['message']);
    }

    public function test_rejected_sorting_center_is_forbidden(): void
    {
        $user = new User();
        $user->usertype = 'sorting_center';
        $user->status = 'approved';

        $sortingCenter = new SortingCenter();
        $sortingCenter->approval_status = 'rejected';
        $user->setRelation('sortingCenter', $sortingCenter);

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('rejected', $data['approval_status']);
        $this->assertEquals('Your sorting center account registration was rejected.', $data['message']);
    }

    public function test_approved_sorting_center_is_allowed(): void
    {
        $user = new User();
        $user->usertype = 'sorting_center';
        $user->status = 'approved';

        $sortingCenter = new SortingCenter();
        $sortingCenter->approval_status = 'approved';
        $user->setRelation('sortingCenter', $sortingCenter);

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, function () {
            return response()->json(['message' => 'Access granted.']);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Access granted.', $data['message']);
    }
}
