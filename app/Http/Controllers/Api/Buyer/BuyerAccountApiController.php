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

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:250'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = $user->addresses()->doesntExist() || ! empty($validated['is_default']);

        if ($isDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'phone' => $validated['phone'],
            'region' => $validated['region'] ?? null,
            'province' => $validated['province'] ?? null,
            'city' => $validated['city'],
            'barangay' => $validated['barangay'],
            'street' => $validated['street'],
            'postal_code' => $validated['postal_code'] ?? null,
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'message' => 'Address added successfully.',
            'address' => $address,
        ], 201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->whereKey($id)->firstOrFail();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:250'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully.',
            'address' => $address,
        ]);
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
        $user = $request->user();
        $product = Product::where('status', 'approved')->findOrFail($productId);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $review = Review::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $product->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'order_id' => $validated['order_id'] ?? null,
                'status' => 'approved',
            ]
        );

        // Recalculate product rating
        $avg = Review::where('product_id', $product->id)->where('status', 'approved')->avg('rating');
        $count = Review::where('product_id', $product->id)->where('status', 'approved')->count();
        $product->update([
            'rating' => round($avg, 2),
            'reviews_count' => $count,
        ]);

        return response()->json([
            'message' => 'Thank you for your review!',
            'review' => $review,
        ], 201);
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

