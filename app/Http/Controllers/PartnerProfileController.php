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

        $allowedDocuments = ['id', 'business_permit', 'government_id', 'permit_doc', 'id_doc', 'sec_doc', 'bir_doc', 'license_doc', 'or_cr_doc', 'dti_certificate', 'sec_certificate', 'bir_2303'];
        abort_unless(in_array($document, $allowedDocuments, true), 404);

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

        $filePath = Storage::disk($disk)->path($path);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        abort_if(in_array($extension, ['php', 'html', 'htm', 'js', 'svg'], true), 403, 'Unsupported or restricted document format.');

        return response()->file($filePath, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ])->setPrivate();
    }

    public function rider(Request $request)
    {
        $user = $request->user();
        $rider = $user->rider;
        $sortingCenter = $rider?->sortingCenter;
        $centerId = (int) ($rider?->sorting_center_id ?? 0);

        $availablePickups = [];
        $acceptedPickups = [];
        $codRecords = [];
        $earningsSummary = [
            'pending' => 0.0,
            'available' => 0.0,
            'settled' => 0.0,
            'paid_out' => 0.0,
            'items' => [],
        ];
        $notifications = [];

        if ($rider && $centerId > 0) {
            $availablePickups = \App\Models\Parcel::query()
                ->where('sorting_center_id', $centerId)
                ->whereIn('status', ['PLACED', 'PREPARING'])
                ->whereNull('pickup_rider_id')
                ->whereHas('pickupRequests', fn ($q) => $q->where('status', 'confirmed'))
                ->with(['pickupRequests' => fn ($q) => $q->where('status', 'confirmed')->with('seller:id,name,store_name')])
                ->latest()
                ->limit(25)
                ->get(['id', 'order_id', 'seller_id', 'status', 'order_item_id', 'collection_address', 'delivery_address', 'pickup_rider_id', 'created_at']);

            $acceptedPickups = \App\Models\Parcel::query()
                ->where('pickup_rider_id', $rider->id)
                ->whereIn('status', ['PLACED', 'PREPARING', 'PICKED_UP'])
                ->with(['pickupRequests' => fn ($q) => $q->with('seller:id,name,store_name')])
                ->latest('updated_at')
                ->limit(25)
                ->get(['id', 'order_id', 'seller_id', 'status', 'order_item_id', 'collection_address', 'delivery_address', 'pickup_rider_id', 'updated_at']);
        }

        if ($rider && \Illuminate\Support\Facades\Schema::hasTable('cod_collections')) {
            $codRecords = \App\Models\CodCollection::query()
                ->where('rider_id', $rider->id)
                ->latest()
                ->limit(20)
                ->get();
        }

        if ($rider && \Illuminate\Support\Facades\Schema::hasTable('rider_delivery_earnings')) {
            $earningRows = \App\Models\RiderDeliveryEarning::query()
                ->where('rider_id', $rider->id)
                ->latest()
                ->limit(25)
                ->get();

            $earningsSummary = [
                'pending' => (float) $earningRows->whereIn('status', ['pending', 'unsettled'])->sum('amount'),
                'available' => (float) $earningRows->whereIn('status', ['available', 'confirmed', 'earned'])->sum('amount'),
                'settled' => (float) $earningRows->where('status', 'settled')->sum('amount'),
                'paid_out' => (float) $earningRows->whereIn('status', ['paid', 'paid_out'])->sum('amount'),
                'items' => $earningRows,
            ];
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('rider_notifications')) {
            $notifications = \App\Models\RiderNotification::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(15)
                ->get();
        }

        return Inertia::render('Rider/Dashboard', [
            'rider' => $rider ? array_merge($rider->only(['id', 'name', 'vehicle_type', 'approval_status', 'sorting_center_id', 'is_active']), [
                'company_name' => $sortingCenter?->business_name ?? $sortingCenter?->name ?? 'Logistics',
                'sorting_center' => $sortingCenter?->only(['id', 'business_name', 'name']),
            ]) : null,
            'parcels' => $rider?->assignedParcels()->with('orderItem.order:id,shipping_method')->latest()->limit(30)->get() ?? [],
            'availablePickups' => $availablePickups,
            'acceptedPickups' => $acceptedPickups,
            'codRecords' => $codRecords,
            'earnings' => $earningsSummary,
            'notifications' => $notifications,
        ]);
    }

    public function acceptPickup(Request $request, int $parcel)
    {
        $rider = $request->user()?->rider;
        abort_unless($rider && $rider->sorting_center_id, 403, 'A sorting center assignment is required for pickups.');

        $assigned = app(\App\Services\PickupDispatch::class)->assign(
            $request->user(),
            (int) $rider->sorting_center_id,
            $parcel,
            (int) $rider->id,
            claim: true
        );

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Pickup job accepted successfully.',
                'data' => $assigned,
            ]);
        }

        return back()->with('status', 'Pickup job accepted successfully.');
    }
}
