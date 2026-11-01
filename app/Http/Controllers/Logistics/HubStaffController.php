<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{LogisticsHub, LogisticsHubStaff, User};
use App\Services\{HubAccess, HubNetwork, LogisticsAudit};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\Rules\Password;

class HubStaffController extends Controller
{
    private function center(Request $request): int
    {
        $center = app(HubAccess::class)->owner($request->user());
        app(HubNetwork::class)->requireStorage();
        abort_unless(Schema::hasTable('logistics_hub_staff'), 503, 'Hub staff setup is not available yet. Contact the ALONA administrator.');
        return (int) $center->id;
    }

    private function records(int $center): array
    {
        return ['hubs' => LogisticsHub::where('sorting_center_id', $center)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'staff' => LogisticsHubStaff::where('sorting_center_id', $center)->with(['user:id,name,email', 'hub:id,name,code'])->orderBy('id')->get()];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->records($this->center($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $center = $this->center($request);
        $input = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()], 'hub_id' => ['required', 'integer']]);
        $email = mb_strtolower(trim($input['email']));
        $member = DB::transaction(function () use ($input, $email, $center, $request) {
            $hub = LogisticsHub::where('sorting_center_id', $center)->where('is_active', true)->whereKey($input['hub_id'])->lockForUpdate()->firstOrFail();
            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'This email is already in use.']);
            $user = User::create(['name' => trim($input['name']), 'email' => $email, 'password' => $input['password'], 'usertype' => 'sorting_center', 'status' => 'approved']);
            $member = LogisticsHubStaff::create(['user_id' => $user->id, 'sorting_center_id' => $center, 'logistics_hub_id' => $hub->id, 'is_active' => true]);
            app(LogisticsAudit::class)->record($request->user(), 'hub_staff_created', data: ['staff_user_id' => $user->id, 'hub_id' => $hub->id], centerId: $center);
            return $member->load(['user:id,name,email', 'hub:id,name,code']);
        });
        return response()->json(['message' => 'Hub staff account created. The staff member can sign in with their email and initial password.', 'data' => $member], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $center = $this->center($request);
        $input = $request->validate(['hub_id' => ['required', 'integer'], 'is_active' => ['required', 'boolean']]);
        $member = LogisticsHubStaff::where('sorting_center_id', $center)->findOrFail($id);
        DB::transaction(function () use ($member, $center, $input, $request): void {
            $user = User::whereKey($member->user_id)->lockForUpdate()->firstOrFail();
            $fresh = LogisticsHubStaff::where('sorting_center_id', $center)->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $hub = LogisticsHub::where('sorting_center_id', $center)
                ->when($input['is_active'], fn ($query) => $query->where('is_active', true))->findOrFail($input['hub_id']);
            $fresh->update(['logistics_hub_id' => $hub->id, 'is_active' => $input['is_active']]);
            // Changed assignments and paused accounts must sign in again; old tokens cannot retain access.
            $user->tokens()->delete();
            app(LogisticsAudit::class)->record($request->user(), 'hub_staff_updated', data: ['staff_user_id' => $user->id, 'hub_id' => $hub->id, 'is_active' => $input['is_active']], centerId: $center);
        });
        return response()->json(['message' => 'Staff access updated. Existing API sessions have been revoked.', 'data' => $member->fresh(['user:id,name,email', 'hub:id,name,code'])]);
    }
}
