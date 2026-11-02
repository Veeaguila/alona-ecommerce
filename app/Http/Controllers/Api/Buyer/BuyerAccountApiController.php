<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\BuyerNotification;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BuyerAccountApiController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ADDRESSES
    |--------------------------------------------------------------------------
    */

    public function addresses(Request $request): JsonResponse
    {
        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();

        if ($addresses->isEmpty() && (!empty($user->street_address) || !empty($user->address) || !empty($user->barangay))) {
            $street = $user->street_address ?: $user->address;
            try {
                $newAddr = $user->addresses()->create([
                    'label' => 'Default Address',
                    'recipient_name' => $user->name ?: 'Customer',
                    'recipient_phone' => $user->contact_no ?: '09123456789',
                    'region' => 'Region VII (Central Visayas)',
                    'province' => $user->province ?: 'Bohol',
                    'municipality' => $user->municipality ?: 'Tagbilaran City',
                    'barangay' => $user->barangay ?: 'Poblacion',
                    'street_address' => $street ?: 'Tagbilaran City',
                    'address' => $street ?: 'Tagbilaran City',
                    'postal_code' => $user->postal_code ?: '6300',
                    'is_default' => true,
                ]);
                $addresses = collect([$newAddr]);
            } catch (\Throwable $e) {}
        }

        $formatted = $addresses->map(function ($a) {
            return [
                'id' => $a->id,
                'label' => $a->label,
                'recipient_name' => $a->recipient_name,
                'phone' => $a->recipient_phone ?: ($a->phone ?? ''),
                'recipient_phone' => $a->recipient_phone ?: ($a->phone ?? ''),
                'region' => $a->region,
                'province' => $a->province,
                'city' => $a->municipality ?: ($a->city ?? ''),
                'municipality' => $a->municipality ?: ($a->city ?? ''),
                'barangay' => $a->barangay,
                'street' => $a->street_address ?: ($a->street ?? $a->address ?? ''),
                'street_address' => $a->street_address ?: ($a->street ?? $a->address ?? ''),
                'postal_code' => $a->postal_code,
                'is_default' => (bool) $a->is_default,
            ];
        });

        return response()->json(['addresses' => $formatted]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->addresses()->count() >= 10) {
            return response()->json([
                'message' => 'You cannot save more than 10 addresses. Please remove an old address first.',
            ], 422);
        }

        $validated = $this->addressData($request);
        $isDefault = $user->addresses()->doesntExist() || ! empty($validated['is_default']);

        if ($isDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $validated['is_default'] = $isDefault;
        $address = $user->addresses()->create($validated);
        return response()->json([
            'message' => 'Address added successfully.',
            'address' => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->recipient_phone,
                'recipient_phone' => $address->recipient_phone,
                'region' => $address->region,
                'province' => $address->province,
                'city' => $address->municipality,
                'municipality' => $address->municipality,
                'barangay' => $address->barangay,
                'street' => $address->street_address,
                'street_address' => $address->street_address,
                'postal_code' => $address->postal_code,
                'is_default' => (bool) $address->is_default,
            ],
        ], 201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->whereKey($id)->firstOrFail();

        $validated = $this->addressData($request);
        if (! empty($validated['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        if ($address->is_default && ! $request->boolean('is_default')) {
            $validated['is_default'] = true;
        }
        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully.',
            'address' => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->recipient_phone,
                'recipient_phone' => $address->recipient_phone,
                'region' => $address->region,
                'province' => $address->province,
                'city' => $address->municipality,
                'municipality' => $address->municipality,
                'barangay' => $address->barangay,
                'street' => $address->street_address,
                'street_address' => $address->street_address,
                'postal_code' => $address->postal_code,
                'is_default' => (bool) $address->is_default,
            ],
        ]);
    }

    /** Map the original mobile form to the existing web schema and validator. */
    private function addressData(Request $request): array
    {
        $request->merge([
            'recipient_phone' => $request->input('phone', $request->input('recipient_phone')),
            'municipality' => $request->input('city', $request->input('municipality')),
            'street_address' => $request->input('street', $request->input('street_address')),
            'address' => null, // Rebuild the snapshot after every structured edit.
        ]);
        return app(\App\Http\Controllers\BuyerAddressController::class)->validateAddress($request);
    }

    public function setDefaultAddress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->whereKey($id)->firstOrFail();

        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address set.',
            'address' => $address,
        ]);
    }

    public function deleteAddress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->whereKey($id)->firstOrFail();
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $newDefault = $user->addresses()->first();
            $newDefault?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Address deleted successfully.']);
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->buyerNotifications()->latest('id')->take(50)->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->buyerNotifications()->whereNull('read_at')->count(),
        ]);
    }

    public function readNotification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $user->buyerNotifications()->whereKey($id)->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->buyerNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /*
    |--------------------------------------------------------------------------
    | REVIEWS
    |--------------------------------------------------------------------------
    */

    public function reviews(Request $request): JsonResponse
    {
        $user = $request->user();
        $reviews = Review::query()
            ->where('user_id', $user->id)
            ->with(['product:id,name,image_path,price'])
            ->latest('id')
            ->get();

        return response()->json(['reviews' => $reviews]);
    }

    public function storeReview(Request $request, int $productId): JsonResponse
    {
        $product = Product::where('status', 'approved')->findOrFail($productId);
        return app(\App\Http\Controllers\BuyerReviewController::class)->store($request, $product);
    }

    /**
     * Toggle Follow Seller Store
     */
    public function toggleFollowStore(Request $request, int $sellerId): JsonResponse
    {
        $user = $request->user();
        if ($user->id === $sellerId) {
            return response()->json(['message' => 'You cannot follow your own store.'], 422);
        }

        $seller = User::where('id', $sellerId)->where('usertype', 'seller')->firstOrFail();

        $existing = \App\Models\StoreFollower::where('user_id', $user->id)
            ->where('seller_id', $seller->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            \App\Models\StoreFollower::create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'followed_at' => now(),
            ]);
            $following = true;
        }

        $followersCount = \App\Models\StoreFollower::where('seller_id', $seller->id)->count();

        return response()->json([
            'is_following' => $following,
            'followers_count' => $followersCount,
            'message' => $following ? 'You are now following this store.' : 'Unfollowed store.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLAINTS & SUPPORT TICKETS
    |--------------------------------------------------------------------------
    */

    public function complaints(Request $request): JsonResponse
    {
        $user = $request->user();
        $statusTab = strtolower((string) $request->input('status', 'all'));

        $query = \App\Models\Complaint::query()
            ->where('buyer_id', $user->id)
            ->with([
                'seller:id,name,store_name',
                'order:id,order_number',
                'product:id,name',
            ]);

        if ($statusTab !== 'all' && in_array($statusTab, ['pending', 'reviewing', 'resolved', 'rejected'])) {
            $query->where('status', $statusTab);
        }

        $items = $query->latest('id')->take(30)->get();

        $counts = [
            'all' => \App\Models\Complaint::where('buyer_id', $user->id)->count(),
            'pending' => \App\Models\Complaint::where('buyer_id', $user->id)->where('status', 'pending')->count(),
            'reviewing' => \App\Models\Complaint::where('buyer_id', $user->id)->where('status', 'reviewing')->count(),
            'resolved' => \App\Models\Complaint::where('buyer_id', $user->id)->where('status', 'resolved')->count(),
            'rejected' => \App\Models\Complaint::where('buyer_id', $user->id)->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'complaints' => $items,
            'counts' => $counts,
        ]);
    }

    public function storeComplaint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:5000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ]);

        $user = $request->user();
        $complaint = \App\Models\Complaint::create([
            'buyer_id' => $user->id,
            'order_id' => $data['order_id'] ?? null,
            'subject' => $data['subject'],
            'type' => $data['type'] ?? 'complaint',
            'description' => $data['description'],
            'contact_email' => $data['contact_email'] ?? $user->email,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Your complaint/support ticket has been submitted to the platform administration.',
            'complaint' => $complaint,
        ], 201);
    }

    public function contactSupport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:4000'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        \App\Models\Message::create([
            'user_id' => $user->id,
            'subject' => ! empty($data['category']) ? "[{$data['category']}] {$data['subject']}" : $data['subject'],
            'message' => $data['message'],
            'is_read' => false,
        ]);

        $complaint = \App\Models\Complaint::create([
            'buyer_id' => $user->id,
            'subject' => ! empty($data['category'])
                ? "[Support Inquiry - {$data['category']}] {$data['subject']}"
                : "[Support Inquiry] {$data['subject']}",
            'type' => 'support_ticket',
            'description' => $data['message'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Your message has been sent to Alona Customer Support. We will get back to you shortly.',
            'ticket' => $complaint,
        ], 201);
    }
}

