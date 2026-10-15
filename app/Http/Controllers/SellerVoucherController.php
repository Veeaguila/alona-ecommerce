<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerVoucherController extends Controller
{
    /**
     * Display seller vouchers with real-time revenue attribution analytics (SELLER-30).
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

        return Inertia::render('Seller/Vouchers', [
            'vouchers' => $vouchers,
            'analytics' => $analytics,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created voucher with targeting, limits, and caps (SELLER-26 to 29).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);

        if ($data['type'] === 'free_shipping' && empty($data['value'])) {
            $data['value'] = 100.00;
        }

        if ($data['target_type'] !== 'product') {
            $data['targeted_product_ids'] = null;
        }

        $request->user()->vouchers()->create($data);

        return back()->with('status', 'Voucher created successfully.');
    }

    /**
     * Update an existing voucher (SELLER-26 to 29).
     */
    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);

        $data = $this->validated($request, $voucher->id);
        $data['code'] = strtoupper($data['code']);

        if ($data['type'] === 'free_shipping' && empty($data['value'])) {
            $data['value'] = 100.00;
        }

        if ($data['target_type'] !== 'product') {
            $data['targeted_product_ids'] = null;
        }

        $voucher->update($data);

        return back()->with('status', 'Voucher updated successfully.');
    }

    /**
     * Delete an existing voucher.
     */
    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);
        $voucher->delete();

        return back()->with('status', 'Voucher removed.');
    }

    /**
     * Validate request payload for vouchers.
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                'unique:vouchers,code,'.($ignoreId ?? 'NULL').',id,seller_id,'.$request->user()->id,
            ],
            'type' => ['required', 'in:percentage,fixed,free_shipping'],
            'value' => [
                'required_unless:type,free_shipping',
                'nullable',
                'numeric',
                'min:0',
                $request->input('type') === 'percentage'
                    ? 'max:100'
                    : 'max:99999999.99',
            ],
            'max_discount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'target_type' => ['required', 'in:all,product'],
            'targeted_product_ids' => ['nullable', 'array'],
            'targeted_product_ids.*' => ['integer', 'exists:products,id'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);
    }
}
