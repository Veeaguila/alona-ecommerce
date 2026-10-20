<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerVoucherController extends Controller
{
    /**
     * Display seller vouchers with real-time revenue attribution analytics (SELLER-30 & Module S7).
     */
    public function index(Request $request): Response
    {
        $seller = $request->user();

        $vouchers = $seller->vouchers()
            ->withCount([
                'orders as orders_count' => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'userVouchers as claimed_count' => fn ($q) => $q->where('status', 'claimed'),
            ])
            ->withSum(['orders as gross_revenue' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->withSum(['orders as total_discount_spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'discount')
            ->latest()
            ->get();

        $analytics = [
            'total_vouchers' => $vouchers->count(),
            'active_vouchers' => $vouchers->filter(fn ($v) => $v->isValidNow())->count(),
            'total_redemptions' => (int) $vouchers->sum('used_count'),
            'total_revenue_generated' => (float) $vouchers->sum('gross_revenue'),
            'total_discount_funded' => (float) $vouchers->sum('total_discount_spent'),
        ];

        $products = Product::where('seller_id', $seller->id)
            ->select('id', 'name', 'price', 'category_id')
            ->orderBy('name')
            ->get();

        $sales = Schema::hasTable('product_sales')
            ? ProductSale::with('product:id,name,price,image_path')
                ->where('seller_id', $seller->id)
                ->latest('id')
                ->get()
            : collect();

        return Inertia::render('Seller/Vouchers', [
            'vouchers' => $vouchers,
            'sales' => $sales,
            'analytics' => $analytics,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created voucher with targeting, limits, caps, and S7 rules (SEL-VOU-001 to SEL-VOU-008).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper(trim((string) $data['code']));

        if (($data['type'] ?? 'percentage') === 'free_shipping' && empty($data['value'])) {
            $data['value'] = 100.00;
        }

        if (($data['target_type'] ?? 'all') !== 'product') {
            $data['targeted_product_ids'] = null;
        }

        if (! isset($data['display_mode'])) {
            $data['display_mode'] = 'claimable';
        }

        $request->user()->vouchers()->create($data);

        return back()->with('status', 'Voucher created successfully.');
    }

    /**
     * Update an existing voucher (SEL-VOU-009: Lock fields after any buyer claims it).
     */
    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless((int) $voucher->seller_id === (int) $request->user()->id, 403);

        $hasClaims = Schema::hasTable('user_vouchers') && $voucher->userVouchers()->exists();

        if ($hasClaims) {
            return $this->updateClaimedVoucher($request, $voucher);
        }

        $data = $this->validated($request, $voucher->id);
        $data['code'] = strtoupper(trim((string) $data['code']));

        if (($data['type'] ?? 'percentage') === 'free_shipping' && empty($data['value'])) {
            $data['value'] = 100.00;
        }

        if (($data['target_type'] ?? 'all') !== 'product') {
            $data['targeted_product_ids'] = null;
        }

        $voucher->update($data);

        return back()->with('status', 'Voucher updated successfully.');
    }

    /**
     * SEL-VOU-010: End a voucher now.
     * Removes it from the wallets of buyers who have not used it; orders already placed keep their discount.
     */
    public function endNow(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless((int) $voucher->seller_id === (int) $request->user()->id, 403);

        $updatePayload = [
            'is_active' => false,
        ];

        if (Schema::hasColumn('vouchers', 'ended_at')) {
            $updatePayload['ended_at'] = now();
        }

        $voucher->update($updatePayload);

        // Remove from wallets of buyers who claimed it but have not used it (A27 / SEL-VOU-010)
        if (Schema::hasTable('user_vouchers')) {
            UserVoucher::where('voucher_id', $voucher->id)
                ->where('status', 'claimed')
                ->delete();
        }

        return back()->with('status', 'Voucher ended successfully.');
    }

    /**
     * Delete an existing voucher.
     */
    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless((int) $voucher->seller_id === (int) $request->user()->id, 403);
        $voucher->delete();

        return back()->with('status', 'Voucher removed.');
    }

    /**
     * SEL-VOU-009 / A27: Once any buyer has claimed a voucher, the seller can only extend its end date
     * or raise its usage limit (or end/deactivate it); all other fields are locked.
     */
    private function updateClaimedVoucher(Request $request, Voucher $voucher): RedirectResponse
    {
        $errors = [];

        if ($request->has('code') && strtoupper(trim((string) $request->input('code'))) !== strtoupper((string) $voucher->code)) {
            $errors['code'] = 'Voucher code is locked because buyers have already claimed this voucher.';
        }

        if ($request->has('type') && (string) $request->input('type') !== (string) $voucher->type) {
            $errors['type'] = 'Discount type is locked because buyers have already claimed this voucher.';
        }

        if ($request->has('value') && $request->input('value') !== null && $request->input('value') !== '') {
            if (round((float) $request->input('value'), 2) !== round((float) $voucher->value, 2)) {
                $errors['value'] = 'Discount amount is locked and cannot be edited after a buyer has claimed the voucher.';
            }
        }

        if ($request->has('min_spend') && $request->input('min_spend') !== null && $request->input('min_spend') !== '') {
            if (round((float) $request->input('min_spend'), 2) !== round((float) ($voucher->min_spend ?? 0), 2)) {
                $errors['min_spend'] = 'Minimum spend is locked and cannot be edited after a buyer has claimed the voucher.';
            }
        }

        if ($request->has('max_discount') && $request->input('max_discount') !== null && $request->input('max_discount') !== '') {
            if (round((float) $request->input('max_discount'), 2) !== round((float) ($voucher->max_discount ?? 0), 2)) {
                $errors['max_discount'] = 'Maximum discount cap is locked after a buyer has claimed the voucher.';
            }
        }

        if ($request->has('per_customer_limit') && $request->input('per_customer_limit') !== null && $request->input('per_customer_limit') !== '') {
            if ((int) $request->input('per_customer_limit') !== (int) ($voucher->per_customer_limit ?? 1)) {
                $errors['per_customer_limit'] = 'Per-buyer limit is locked after a buyer has claimed the voucher.';
            }
        }

        $updateData = [];

        if ($request->filled('expires_at')) {
            $newEnd = \Carbon\Carbon::parse($request->input('expires_at'))->startOfDay();
            if ($voucher->expires_at && $newEnd->lt($voucher->expires_at->copy()->startOfDay())) {
                $errors['expires_at'] = 'End date can only be extended for a claimed voucher.';
            } else {
                $updateData['expires_at'] = $newEnd->toDateString();
            }
        }

        if ($request->filled('usage_limit')) {
            $newLimit = (int) $request->input('usage_limit');
            $oldLimit = (int) ($voucher->usage_limit ?? 0);
            if ($newLimit < $oldLimit || $newLimit < 1) {
                $errors['usage_limit'] = 'Total usage limit can only be raised for a claimed voucher.';
            } else {
                $updateData['usage_limit'] = $newLimit;
            }
        }

        if ($request->has('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $updateData['is_active'] = $isActive;
            if (! $isActive) {
                if (Schema::hasColumn('vouchers', 'ended_at')) {
                    $updateData['ended_at'] = now();
                }
                UserVoucher::where('voucher_id', $voucher->id)
                    ->where('status', 'claimed')
                    ->delete();
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if (! empty($updateData)) {
            $voucher->update($updateData);
        }

        return back()->with('status', 'Voucher updated successfully.');
    }

    /**
     * Validate request payload for vouchers (SEL-VOU-004, SEL-VOU-005, SEL-VOU-006, SEL-VOU-007).
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rawCode = (string) $request->input('code', '');

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId) {
                    $str = (string) $value;
                    // SEL-VOU-004 / A26: 4 to 12 letters and digits, no spaces or symbols
                    if (! preg_match('/^[A-Za-z0-9]{4,12}$/', $str)) {
                        $fail('Voucher code must be 4 to 12 letters and digits with no spaces or symbols.');
                        return;
                    }

                    // SEL-VOU-005 / A26: Codes are unique across the whole platform (case-insensitive)
                    $exists = Voucher::query()
                        ->whereRaw('LOWER(code) = ?', [strtolower($str)])
                        ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('This voucher code is already in use across the platform.');
                    }
                },
            ],
            'type' => ['required', 'in:percentage,fixed,free_shipping'],
            'value' => [
                'required_unless:type,free_shipping',
                'nullable',
                'numeric',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $type = $request->input('type');
                    if ($type === 'free_shipping') {
                        return;
                    }

                    $num = (float) $value;
                    if ($num <= 0) {
                        $fail('Discount value must be greater than zero.');
                        return;
                    }

                    if ($type === 'percentage') {
                        if ($num < 1 || $num > 99) {
                            $fail('Percentage discount must be between 1% and 99%.');
                        }
                    } else {
                        if ($num > 99999999.99) {
                            $fail('Discount amount is too large.');
                        }
                    }
                },
            ],
            'max_discount' => [
                'nullable',
                'numeric',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && (float) $value <= 0) {
                        $fail('Maximum discount cap must be greater than ₱0.');
                    }
                },
            ],
            'min_spend' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'usage_limit' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_customer_limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
            'target_type' => ['nullable', 'in:all,product'],
            'display_mode' => ['nullable', 'in:claimable,code_only'],
            'targeted_product_ids' => ['nullable', 'array'],
            'targeted_product_ids.*' => ['integer', 'exists:products,id'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $errors = [];

        // SEL-VOU-006: Discount cannot exceed the minimum spend (when min_spend > 0 and type is fixed)
        if (($data['type'] ?? '') === 'fixed' && isset($data['min_spend']) && $data['min_spend'] !== null && $data['min_spend'] !== '') {
            $minSpend = (float) $data['min_spend'];
            $discountAmount = (float) ($data['value'] ?? 0);
            if ($minSpend > 0 && $discountAmount > $minSpend) {
                $errors['value'] = 'The discount cannot exceed the minimum spend.';
            }
        }

        // SEL-VOU-007: Per buyer limit cannot exceed total usage limit
        if (! empty($data['usage_limit']) && ! empty($data['per_customer_limit'])) {
            if ((int) $data['per_customer_limit'] > (int) $data['usage_limit']) {
                $errors['per_customer_limit'] = 'Per-buyer usage limit cannot exceed the total usage limit.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $data['target_type'] = $data['target_type'] ?? 'all';
        $data['display_mode'] = $data['display_mode'] ?? 'claimable';
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        return $data;
    }
}
