<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BuyerVoucherApiController extends Controller
{
    /**
     * Get buyer vouchers (claimed & available to claim).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->startOfDay();

        // 1. Claimed active vouchers
        $claimedVouchers = UserVoucher::query()
            ->where('user_id', $user->id)
            ->where('status', 'claimed')
            ->with(['voucher.seller:id,name'])
            ->get()
            ->map(fn ($uv) => $this->formatVoucher($uv->voucher, true));

        // 2. Available vouchers to claim
        $claimedIds = UserVoucher::query()
            ->where('user_id', $user->id)
            ->pluck('voucher_id')
            ->toArray();

        $browseVouchers = Voucher::query()
            ->with(['seller:id,name'])
            ->where('is_active', true)
            ->whereNotIn('id', $claimedIds)
            ->where(function ($q) use ($today) {
                $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->latest('id')
            ->take(30)
            ->get()
            ->map(fn ($v) => $this->formatVoucher($v, false));

        return response()->json([
            'claimed' => $claimedVouchers,
            'available' => $browseVouchers,
        ]);
    }

    /**
     * Claim a voucher.
     */
    public function claim(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $voucher = Voucher::where('is_active', true)->findOrFail($id);

        $exists = UserVoucher::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already claimed this voucher.'], 422);
        }

        if ($voucher->usage_limit !== null && (int) $voucher->used_count >= (int) $voucher->usage_limit) {
            return response()->json(['message' => 'This voucher has reached its usage limit.'], 422);
        }

        UserVoucher::create([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Voucher claimed successfully!',
            'voucher' => $this->formatVoucher($voucher, true),
        ]);
    }

    /**
     * Validate voucher code for checkout.
     */
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'shipping_method' => ['nullable', 'string'],
        ]);

        $code = strtoupper(trim($data['code']));
        $user = $request->user();

        $voucher = Voucher::where('code', $code)->where('is_active', true)->first();
        if (! $voucher) {
            return response()->json(['valid' => false, 'message' => 'Invalid or inactive voucher code.'], 422);
        }

        if ($voucher->starts_at && now()->lt($voucher->starts_at)) {
            return response()->json(['valid' => false, 'message' => 'This voucher is not active yet.'], 422);
        }

        if ($voucher->expires_at && now()->gt($voucher->expires_at)) {
            return response()->json(['valid' => false, 'message' => 'This voucher has expired.'], 422);
        }

        $items = $user->cartItems()
            ->whereIn('id', $data['cart_item_ids'])
            ->with(['product', 'variant'])
            ->get();

        $subtotal = $items->sum(function ($item) {
            $price = $item->variant ? (float) $item->variant->price : (float) ($item->product?->price ?? 0);
            return $price * (int) $item->quantity;
        });

        if ($voucher->min_spend !== null && $subtotal < (float) $voucher->min_spend) {
            return response()->json([
                'valid' => false,
                'message' => 'Minimum spend of ₱' . number_format($voucher->min_spend, 2) . ' is required for this voucher.',
            ], 422);
        }

        $discount = 0.0;
        if ($voucher->type === 'percentage') {
            $discount = ($subtotal * (float) $voucher->value) / 100;
            if ($voucher->max_discount !== null && $discount > (float) $voucher->max_discount) {
                $discount = (float) $voucher->max_discount;
            }
        } elseif ($voucher->type === 'fixed') {
            $discount = min($subtotal, (float) $voucher->value);
        } elseif ($voucher->type === 'free_shipping') {
            $discount = min(50.0, (float) $voucher->value ?: 50.0);
        }

        return response()->json([
            'valid' => true,
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'type' => $voucher->type,
                'value' => (float) $voucher->value,
                'discount' => round($discount, 2),
                'min_spend' => (float) ($voucher->min_spend ?? 0),
            ],
        ]);
    }

    private function formatVoucher(?Voucher $v, bool $isClaimed): ?array
    {
        if (! $v) return null;
        return [
            'id' => $v->id,
            'code' => $v->code,
            'type' => $v->type,
            'value' => (float) $v->value,
            'min_spend' => (float) ($v->min_spend ?? 0),
            'max_discount' => (float) ($v->max_discount ?? 0),
            'starts_at' => $v->starts_at?->format('Y-m-d'),
            'expires_at' => $v->expires_at?->format('Y-m-d'),
            'is_claimed' => $isClaimed,
            'is_platform' => (bool) ($v->is_platform || $v->seller_id === null),
            'seller_name' => $v->seller?->name ?? 'Platform',
        ];
    }
}

