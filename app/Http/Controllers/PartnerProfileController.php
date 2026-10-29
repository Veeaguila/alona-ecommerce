<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PartnerProfileController extends Controller
{
    public function status(Request $request, AccountDestination $destinations)
    {
        $user = $request->user();
        $profile = $user->usertype === 'sorting_center' ? $user->sortingCenter : $user->rider;
        $data = ['name' => $user->name, 'email' => $user->email, 'role' => $user->usertype,
            'status' => $user->status, 'approvalStatus' => $profile?->approval_status ?? $user->status,
            'verified' => $user->hasVerifiedEmail(), 'reason' => $user->rejection_reason,
            'destination' => $destinations->route($user) === 'application.status' ? null : route($destinations->route($user)),
            'profile' => $user->partnerApplication?->only(['details', 'address'])];
        $data['usesOtp'] = ($user->partnerApplication?->details['registration_version'] ?? null) === 2;
        if (app(\App\Services\HubAccess::class)->membership($user)) {
            $context = app(\App\Services\HubAccess::class)->context($user);
            $data['approvalStatus'] = $context['center']->approval_status;
            $data['operator'] = app(\App\Services\HubAccess::class)->identity($user);
        }

        if ($user->usertype === 'rider') {
            $data['is_active'] = $profile?->is_active ?? false;
            $data['verification_required'] = $user->partnerApplication()->exists();
            $photo = is_string($user->avatar) && str_starts_with($user->avatar, 'rider-avatars/'.$user->id.'/');
            $data['rider_profile'] = [
                'contact_no' => $user->contact_no ?: $profile?->contact,
                'birthday' => $profile?->birthday?->format('Y-m-d') ?? $user->birthday?->format('Y-m-d'),
                'address' => $user->address ?: implode(', ', array_filter([$profile?->house_number, $profile?->street, $profile?->barangay, $profile?->municipality, $profile?->province])),
                'address_fields' => $user->partnerApplication?->address ?? $user->address_geography,
                'vehicle_type' => $profile?->vehicle_type, 'plate_number' => $profile?->plate_number,
                'photo_available' => $photo && Storage::disk('local')->exists($user->avatar),
                'public_photo_url' => !$photo && is_string($user->avatar) && filter_var($user->avatar, FILTER_VALIDATE_URL)
                    && parse_url($user->avatar, PHP_URL_SCHEME) === 'https' ? $user->avatar : null,
            ];
        }

        return $request->expectsJson() ? response()->json(['data' => $data]) : Inertia::render('Auth/ApplicationStatus', $data);
    }

    public function update(Request $request)
    {
        $application = $request->user()->partnerApplication;
        abort_unless($application, 404);
        // Changes to verified identity, coverage, vehicle or documents require a new review.
        $input = $request->validate(['contact_no' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'operating_hours' => [$request->user()->usertype === 'rider' ? 'exclude' : 'required', 'string', 'max:500']]);
        DB::transaction(function () use ($application, $input, $request) {
            $application->update(['details' => array_merge($application->details, $input)]);
            $request->user()->update(['contact_no' => $input['contact_no']]);
            $request->user()->rider?->update(['contact' => $input['contact_no']]);
        });

        return $request->expectsJson() ? response()->json(['message' => 'Profile updated.']) : back()->with('status', 'Profile updated.');
    }

    public function document(Request $request, User $user, string $document)
    {
        $reviewer = $request->user();
        $ownsRider = $reviewer->usertype === 'sorting_center' && $reviewer->status === 'approved'
            && $reviewer->sortingCenter?->approval_status === 'approved'
            && $user->rider?->sorting_center_id === $reviewer->sortingCenter?->id;
        abort_unless($reviewer->id === $user->id || ($reviewer->usertype === 'admin' && $reviewer->status === 'approved') || $ownsRider, 403);
        $path = $user->partnerApplication?->documents[$document] ?? null;
        if (! $path) {
            if ($document === 'business_permit') {
                $path = $user->business_permit_path;
            } elseif (in_array($document, ['id', 'government_id'], true)) {
                $path = $user->id_path;
            }
        }
        $disk = ($path && Storage::disk('local')->exists($path))
            ? 'local'
            : (($path && Storage::disk('public')->exists($path)) ? 'public' : null);
        abort_unless($path && $disk, 404);

        return response()->file(Storage::disk($disk)->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    public function rider(Request $request)
    {
        $rider = $request->user()->rider;
        $sortingCenter = $rider?->sortingCenter;

        return Inertia::render('Rider/Dashboard', [
            'rider' => $rider ? array_merge($rider->only(['name', 'vehicle_type', 'approval_status']), [
                'company_name' => $sortingCenter?->business_name ?? $sortingCenter?->name ?? 'Logistics',
                'sorting_center' => $sortingCenter?->only(['id', 'business_name', 'name']),
            ]) : null,
            'parcels' => $rider?->assignedParcels()->with('orderItem.order:id,shipping_method')->latest()->limit(30)->get() ?? [],
        ]);
    }
}
