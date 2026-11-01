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

        return response()->json(['addresses' => $addresses]);
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
            'address' => $address,
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

        if ($address->is_default && !$request->boolean('is_default')) $validated['is_default'] = true;
        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully.',
            'address' => $address,
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
}

