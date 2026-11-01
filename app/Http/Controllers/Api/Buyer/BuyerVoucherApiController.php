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
            ->whereHas('voucher', fn ($query) => $this->availableNow($query))
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
            ->whereNull('ended_at')
            ->where(fn ($query) => $query->whereNull('display_mode')->orWhere('display_mode', '!=', 'code_only'))
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
        $voucher = Voucher::findOrFail($id);
        if (!$voucher->isClaimableByBuyer()) {
            return response()->json(['message' => 'This voucher is no longer active or cannot be claimed directly.'], 422);
        }

        $exists = UserVoucher::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already claimed this voucher.'], 422);
        }

        if ($voucher->usage_limit !== null && (int) $voucher->used_count >= (int) $voucher->usage_limit) {
            return response()->json(['message' => 'This voucher has reached its usage limit.'], 422);
        }

        $claim = UserVoucher::firstOrCreate([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
        ], [
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);
        if (!$claim->wasRecentlyCreated) {
            return response()->json(['message' => 'You have already claimed this voucher.'], 422);
        }

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
        $request->validate(['code' => ['required', 'string', 'max:50']]);
        $request->merge(['voucher_code' => $request->input('code')]);
        $quote = app(BuyerOrderApiController::class)->checkoutPreview($request)->getData(true);
        return response()->json(['valid' => true, 'voucher' => $quote['voucher'][0]]);
    }

    private function availableNow($query)
    {
        return $query->where('is_active', true)->whereNull('ended_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
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

