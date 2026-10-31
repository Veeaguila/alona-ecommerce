<?php

namespace App\Services;

use App\Models\{LogisticsHub, LogisticsHubStaff, SortingCenter, User};
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** A staff membership grants access to one company's hub, never company ownership. */
class HubAccess
{
    public function membership(User $user): ?LogisticsHubStaff
    {
        if (!$user->exists || $user->usertype !== 'sorting_center' || !Schema::hasTable('logistics_hub_staff')) return null;
        return LogisticsHubStaff::where('user_id', $user->id)->first();
    }

    public function owner(User $user): SortingCenter
    {
        abort_unless($user->usertype === 'sorting_center' && !$this->membership($user), 403, 'Company administration is restricted to the company owner.');
        $center = $user->sortingCenter;
        abort_unless($user->status === 'approved' && $center?->approval_status === 'approved'
            && (!$user->partnerApplication()->exists() || $user->hasVerifiedEmail()), 403, 'Your company account is unavailable.');
        return $center;
    }

    public function context(User $user, ?int $hubId = null, bool $requireHub = false): array
    {
        abort_unless($user->usertype === 'sorting_center' && $user->status === 'approved', 403, 'An active Logistics account is required.');
        $staff = $this->membership($user);
        if ($staff) {
            abort_unless($staff->is_active, 403, 'Your hub staff access is paused. Contact your company administrator.');
            $center = SortingCenter::availableForEnrollment()->find($staff->sorting_center_id);
            abort_unless($center, 403, 'Your company account is unavailable.');
            abort_if($hubId && $hubId !== (int) $staff->logistics_hub_id, 403, 'Use your assigned operating hub. Refresh after an assignment change.');
            $hubId = (int) $staff->logistics_hub_id;
        } else {
            $center = $this->owner($user);
        }
        $hub = $hubId ? LogisticsHub::where('sorting_center_id', $center->id)->where('is_active', true)->find($hubId) : null;
        abort_if($hubId && !$hub, 403, 'This operating hub is unavailable for your account.');
        if ($requireHub && !$hub) throw ValidationException::withMessages(['hub_id' => 'Choose your operating hub first.']);
        return ['center' => $center, 'hub' => $hub, 'staff' => $staff];
    }

    public function identity(User $user): array
    {
        $context = $this->context($user);
        return ['role' => $context['staff'] ? 'hub_staff' : 'company_admin',
            'hub' => $context['hub']?->only(['id', 'name', 'code', 'address'])];
    }
}
