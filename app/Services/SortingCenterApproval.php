<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SortingCenterApproval
{
    // Call inside the approval transaction while holding the user's row lock.
    public function approve(User $user): void
    {
        if (app(HubAccess::class)->membership($user)) {
            return;
        }

        $center = $user->sortingCenter()->first();
        $application = $user->partnerApplication;
        if ($application) {
            abort_unless($user->hasVerifiedEmail(), 409, 'Verify the logistics email before approval.');
            if (($application->details['registration_version'] ?? null) === 2) {
                $reactivating = in_array($user->status, ['suspended', 'deactivated'], true)
                    && $center?->approval_status === 'approved';
                abort_unless(in_array($user->status, ['pending', 'approved'], true) || $reactivating, 409,
                    'Only verified pending logistics applications can be approved.');
            }
        }

        if ($center) {
            $center->update(['approval_status' => 'approved']);
            return;
        }

        // Legacy accounts can predate the company record. Reuse their stored identity.
        $name = trim((string) ($application?->details['business_name'] ?? $user->name));
        if ($name === '') {
            throw ValidationException::withMessages(['status' => 'A logistics business name is required before approval.']);
        }

        $user->sortingCenter()->create([
            'business_name' => $name,
            'approval_status' => 'approved',
            'id_doc_url' => $application?->documents['id_doc'] ?? $user->id_path,
            'permit_doc_url' => $application?->documents['permit_doc'] ?? $user->business_permit_path,
        ]);
    }
}
