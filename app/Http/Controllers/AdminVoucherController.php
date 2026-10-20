<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminVoucherController extends Controller
{
    /**
     * Display platform vouchers and campaigns.
     */
    public function index(Request $request): Response
    {
        $query = Voucher::query()
            ->where(function ($q) {
                $q->where('is_platform', true)
                  ->orWhereNull('seller_id');
            })
            ->withCount('userVouchers');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $vouchers = $query->latest()
            ->paginate(15)
            ->withQueryString();

        $campaigns = Announcement::query()
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Vouchers', [
            'title' => 'Platform Vouchers & Campaigns',
            'eyebrow' => 'Promotions Engine',
            'description' => 'Create platform-wide discount codes, free shipping vouchers, and promotional campaigns.',
            'active' => 'vouchers',
            'vouchers' => $vouchers,
            'campaigns' => $campaigns,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * Store a newly created platform voucher.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code'],
            'type' => ['required', 'in:fixed,percentage,free_shipping'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $voucher = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => strtoupper(trim($validated['code'])),
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_spend' => $validated['min_spend'] ?? 0,
            'max_discount' => $validated['max_discount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? 100,
            'per_customer_limit' => $validated['per_customer_limit'] ?? 1,
            'target_type' => 'all',
            'display_mode' => 'public',
            'starts_at' => $validated['starts_at'] ?? now()->toDateString(),
            'expires_at' => $validated['expires_at'] ?? now()->addMonth()->toDateString(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AdminAuditLog::record(
            $request->user(),
            'voucher_created',
            "Created site-wide platform voucher '{$voucher->code}' ({$voucher->type}: ₱{$voucher->value})",
            $voucher,
            ['code' => $voucher->code, 'type' => $voucher->type, 'value' => $voucher->value]
        );

        return back()->with('status', "Platform voucher '{$voucher->code}' created successfully.");
    }

    /**
     * Update an existing platform voucher.
     */
    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code,' . $voucher->id],
            'type' => ['required', 'in:fixed,percentage,free_shipping'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldData = $voucher->only(['code', 'type', 'value', 'is_active']);

        $voucher->update([
            'code' => strtoupper(trim($validated['code'])),
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_spend' => $validated['min_spend'] ?? $voucher->min_spend,
            'max_discount' => $validated['max_discount'] ?? $voucher->max_discount,
            'usage_limit' => $validated['usage_limit'] ?? $voucher->usage_limit,
            'per_customer_limit' => $validated['per_customer_limit'] ?? $voucher->per_customer_limit,
            'starts_at' => $validated['starts_at'] ?? $voucher->starts_at,
            'expires_at' => $validated['expires_at'] ?? $voucher->expires_at,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $voucher->is_active,
        ]);

        AdminAuditLog::record(
            $request->user(),
            'voucher_updated',
            "Updated platform voucher '{$voucher->code}'",
            $voucher,
            ['old' => $oldData, 'new' => $voucher->only(['code', 'type', 'value', 'is_active'])]
        );

        return back()->with('status', "Platform voucher '{$voucher->code}' updated successfully.");
    }

    /**
     * Delete a platform voucher.
     */
    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        $code = $voucher->code;
        $voucher->delete();

        AdminAuditLog::record(
            $request->user(),
            'voucher_deleted',
            "Deleted platform voucher '{$code}'",
            null,
            ['code' => $code]
        );

        return back()->with('status', "Platform voucher '{$code}' deleted successfully.");
    }
}

