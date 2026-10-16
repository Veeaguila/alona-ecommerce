<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhilippineAddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage};

class RiderProfileController extends Controller
{
    private function own(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->usertype === 'rider' && $user->rider, 403);
        return $user;
    }

    public function update(Request $request, PhilippineAddressService $addresses)
    {
        $user = $this->own($request);
        $input = $request->validate([
            'contact_no' => ['sometimes', 'required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'address' => ['sometimes', 'required', 'array:region_code,province_code,city_code,barangay_code,street,house_number,subdivision,postal_code'],
            'address.region_code' => 'required_with:address|string|max:10',
            'address.province_code' => 'nullable|string|max:10',
            'address.city_code' => 'required_with:address|string|max:10',
            'address.barangay_code' => 'required_with:address|string|max:10',
            'address.street' => 'required_with:address|string|max:255',
            'address.house_number' => 'nullable|string|max:100',
            'address.subdivision' => 'nullable|string|max:255',
            'address.postal_code' => ['nullable', 'regex:/^\d{4}$/'],
            'name' => 'prohibited', 'email' => 'prohibited', 'birthday' => 'prohibited',
            'vehicle_type' => 'prohibited', 'plate_number' => 'prohibited', 'role' => 'prohibited',
            'usertype' => 'prohibited', 'status' => 'prohibited', 'approval_status' => 'prohibited',
            'is_active' => 'prohibited', 'sorting_center_id' => 'prohibited', 'earnings' => 'prohibited',
        ]);
        abort_if(!$input, 422, 'No permitted profile changes supplied.');
        $address = isset($input['address']) ? $addresses->validate(array_merge(['province_code' => '', 'house_number' => '',
            'subdivision' => '', 'postal_code' => ''], array_filter($input['address'], fn ($value) => $value !== null))) : null;
        $review = DB::transaction(function () use ($user, $input, $address): bool {
            // Use the same rider -> user lock order as Logistics approval/rejection.
            $rider = $user->rider()->lockForUpdate()->firstOrFail();
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $application = $lockedUser->partnerApplication()->lockForUpdate()->first();
            if (isset($input['contact_no'])) {
                $lockedUser->contact_no = $input['contact_no'];
                $rider->contact = $input['contact_no'];
                if ($application) $application->details = array_merge($application->details ?? [], ['contact_no' => $input['contact_no']]);
            }
            $previous = array_merge(['house_number' => '', 'subdivision' => '', 'postal_code' => ''], $application?->address ?? $lockedUser->address_geography ?? []);
            $changed = $address !== null && $address != $previous;
            if ($changed) {
                $full = implode(', ', array_filter([$address['house_number'], $address['street'], $address['subdivision'], $address['barangay'], $address['city'], $address['province'], $address['region'], $address['postal_code']]));
                $lockedUser->fill(['address' => $full, 'address_geography' => $address, 'province' => $address['province'],
                    'municipality' => $address['city'], 'barangay' => $address['barangay'], 'street_address' => $address['street'],
                    'status' => 'pending', 'rejection_reason' => null]);
                $rider->fill(['province' => $address['province'], 'municipality' => $address['city'], 'barangay' => $address['barangay'],
                    'street' => $address['street'], 'house_number' => $address['house_number'], 'approval_status' => 'pending']);
                if ($application) {
                    $application->address = $address;
                    $application->details = array_merge($application->details ?? [], $address);
                }
            }
            $lockedUser->save();
            $rider->save();
            $application?->save();
            return $changed;
        });

        return response()->json(['message' => $review ? 'Address updated. Logistics reapproval is required before operational access resumes.' : 'Profile updated.', 'requires_review' => $review]);
    }

    public function photo(Request $request)
    {
        $user = $this->own($request);
        $path = $user->avatar;
        abort_unless(is_string($path) && str_starts_with($path, 'rider-avatars/'.$user->id.'/') && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function uploadPhoto(Request $request)
    {
        $user = $this->own($request);
        $request->validate(['photo' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:max_width=2000,max_height=2000']]);
        $newPath = null;
        try {
            DB::transaction(function () use ($user, $request, &$newPath): void {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $oldPath = $locked->avatar;
                $newPath = $request->file('photo')->store('rider-avatars/'.$user->id, 'local');
                if (!$newPath) throw new \RuntimeException('Photo storage failed.');
                $locked->update(['avatar' => $newPath]);
                DB::afterCommit(function () use ($oldPath, $user): void {
                    if (is_string($oldPath) && str_starts_with($oldPath, 'rider-avatars/'.$user->id.'/')) {
                        try { Storage::disk('local')->delete($oldPath); }
                        catch (\Throwable $failure) { Log::warning('Old rider photo cleanup failed.', ['user_id' => $user->id]); }
                    }
                });
            });
        } catch (\Throwable $failure) {
            if ($newPath) Storage::disk('local')->delete($newPath);
            throw $failure;
        }
        return response()->json(['message' => 'Profile picture updated.']);
    }
}
