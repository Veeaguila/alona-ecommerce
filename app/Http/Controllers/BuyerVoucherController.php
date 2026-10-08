<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerVoucherController extends Controller
{
    /**
     * Show the buyer's vouchers page with claimed, used, expired, and browse tabs.
     * Also checks and sends alerts for vouchers expiring soon (BUYER-27).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = strtolower((string) $request->input('tab', 'claimed'));

        // Check for expiring vouchers and notify the buyer (BUYER-27)
        $this->checkAndNotifyExpiringVouchers($user);

        // Update any claimed vouchers that have passed expiration date
        UserVoucher::query()
            ->where('user_id', $user->id)
            ->where('status', 'claimed')
            ->whereHas('voucher', function ($query) {
                $query->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<', now()->toDateString());
            })
            ->update(['status' => 'expired']);

        // Precompute counts
        $counts = [
            'claimed' => UserVoucher::query()
                ->where('user_id', $user->id)
                ->where('status', 'claimed')
                ->count(),
            'used' => UserVoucher::query()
                ->where('user_id', $user->id)
                ->where('status', 'used')
                ->count(),
            'expired' => UserVoucher::query()
                ->where('user_id', $user->id)
                ->where('status', 'expired')
                ->count(),
            'browse' => $this->availableVouchersQuery($user)->count(),
        ];

        $myVouchers = null;
        $browseVouchers = null;

        if ($tab === 'browse') {
            // Browse available seller & platform vouchers to claim (BUYER-24)
            $browseVouchers = $this->availableVouchersQuery($user)
                ->with('seller:id,name')
                ->latest()
                ->paginate(12)
                ->withQueryString();
        } else {
            // Claimed, used, or expired vouchers (BUYER-26)
            $statusFilter = in_array($tab, ['claimed', 'used', 'expired'], true) ? $tab : 'claimed';

            $myVouchers = UserVoucher::query()
                ->where('user_id', $user->id)
                ->where('status', $statusFilter)
                ->with([
                    'voucher.seller:id,name',
                ])
                ->latest('claimed_at')
                ->paginate(12)
                ->withQueryString();
        }

        return Inertia::render('Buyer/Vouchers', [
            'my_vouchers' => $myVouchers,
            'browse_vouchers' => $browseVouchers,
            'current_tab' => $tab,
            'counts' => $counts,
        ]);
    }

    /**
     * Buyer claims an available voucher to their account (BUYER-25).
     */
    public function claim(Request $request, Voucher $voucher): RedirectResponse
    {
        $user = $request->user();

        if (!$voucher->isValidNow()) {
            throw ValidationException::withMessages([
                'voucher' => 'This voucher is no longer active or has reached its usage limit.',
            ]);
        }

        $alreadyClaimed = UserVoucher::query()
            ->where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->exists();

        if ($alreadyClaimed) {
            throw ValidationException::withMessages([
                'voucher' => 'You have already claimed this voucher.',
            ]);
        }

        UserVoucher::create([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);

        if (Schema::hasTable('buyer_notifications')) {
            $discountText = $voucher->type === 'percentage'
                ? "{$voucher->value}% off"
                : "₱" . number_format((float) $voucher->value, 2) . " off";

            BuyerNotification::create([
                'user_id' => $user->id,
                'title' => 'Voucher Claimed!',
                'message' => "You claimed voucher code {$voucher->code} ({$discountText}). Use it at checkout before it expires!",
            ]);
        }

        return back()->with(
            'status',
            "Voucher {$voucher->code} claimed successfully! You can apply it at checkout."
        );
    }

    /**
     * Helper to get available vouchers that the buyer has not yet claimed.
     */
    private function availableVouchersQuery($user)
    {
        $claimedVoucherIds = UserVoucher::query()
            ->where('user_id', $user->id)
            ->pluck('voucher_id');

        return Voucher::query()
            ->where('is_active', true)
            ->whereNotIn('id', $claimedVoucherIds)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereRaw('used_count < usage_limit');
            });
    }

    /**
     * Check for claimed vouchers expiring within 3 days and notify the buyer (BUYER-27).
     */
    private function checkAndNotifyExpiringVouchers($user): void
    {
        if (!Schema::hasTable('buyer_notifications')) {
            return;
        }

        $soonThreshold = now()->addDays(3)->toDateString();

        $expiringClaims = UserVoucher::query()
            ->where('user_id', $user->id)
            ->where('status', 'claimed')
            ->whereNull('expiring_alert_sent_at')
            ->whereHas('voucher', function ($query) use ($soonThreshold) {
                $query->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', now()->toDateString())
                    ->whereDate('expires_at', '<=', $soonThreshold);
            })
            ->with('voucher')
            ->get();

        foreach ($expiringClaims as $claim) {
            $voucher = $claim->voucher;
            $daysLeft = now()->diffInDays($voucher->expires_at, false) + 1;

            $message = $daysLeft <= 1
                ? "Your voucher {$voucher->code} expires today! Don't miss out on your discount."
                : "Your voucher {$voucher->code} will expire in {$daysLeft} days.";

            BuyerNotification::create([
                'user_id' => $user->id,
                'title' => 'Expiring Voucher Alert',
                'message' => $message,
            ]);

            $claim->update([
                'expiring_alert_sent_at' => now(),
            ]);
        }
    }
}

