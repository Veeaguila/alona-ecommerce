<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SellerPromotion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerPromotionController extends Controller
{
    /**
     * Create a Bundle Deal (SEL-FLASH-007) or Buy X Get Y promotion (SEL-FLASH-008).
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $type = $request->input('type');
        if (! $type) {
            $type = ($request->has('buy_product_id') || $request->has('get_product_id'))
                ? 'buy_x_get_y'
                : 'bundle';
        }

        $startsAt = $request->filled('starts_at') ? Carbon::parse($request->input('starts_at')) : now();
        $endsAt = $request->filled('ends_at') ? Carbon::parse($request->input('ends_at')) : null;

        if ($endsAt && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The promotion end date must be after the start date.',
            ]);
        }

        if ($type === 'buy_x_get_y') {
            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:120'],
                'buy_product_id' => ['required', 'integer', 'exists:products,id'],
                'buy_quantity' => ['required', 'integer', 'min:1'],
                'get_product_id' => ['required', 'integer', 'exists:products,id'],
                'get_quantity' => ['nullable', 'integer', 'min:1'],
                'get_discount_percentage' => ['nullable', 'numeric', 'min:1', 'max:100'],
            ]);

            $buyProduct = Product::where('id', $validated['buy_product_id'])
                ->where('seller_id', $seller->id)
                ->first();
            $getProduct = Product::where('id', $validated['get_product_id'])
                ->where('seller_id', $seller->id)
                ->first();

            abort_unless($buyProduct !== null && $getProduct !== null, 403);

            SellerPromotion::create([
                'seller_id' => $seller->id,
                'name' => $validated['name'] ?? ("Buy {$validated['buy_quantity']} {$buyProduct->name}, Get " . ($validated['get_quantity'] ?? 1) . " {$getProduct->name} Free"),
                'type' => 'buy_x_get_y',
                'buy_product_id' => $buyProduct->id,
                'buy_quantity' => (int) $validated['buy_quantity'],
                'get_product_id' => $getProduct->id,
                'get_quantity' => (int) ($validated['get_quantity'] ?? 1),
                'get_discount_percentage' => (float) ($validated['get_discount_percentage'] ?? 100.00),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => true,
            ]);

            return back()->with('status', 'Buy X Get Y promotion created successfully.');
        }

        // Bundle deal (SEL-FLASH-007)
        if (! $request->has('discount_value')) {
            if ($request->has('discount_percentage')) {
                $request->merge(['discount_value' => $request->input('discount_percentage'), 'discount_type' => 'percentage']);
            } elseif ($request->has('percentage')) {
                $request->merge(['discount_value' => $request->input('percentage'), 'discount_type' => 'percentage']);
            } elseif ($request->has('value')) {
                $request->merge(['discount_value' => $request->input('value')]);
            }
        }

        if (! $request->has('min_quantity') && $request->has('quantity')) {
            $request->merge(['min_quantity' => $request->input('quantity')]);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'min_quantity' => ['required', 'integer', 'min:2'],
            'discount_type' => ['nullable', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:1'],
        ]);

        $productIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                ! empty($validated['product_id']) ? [$validated['product_id']] : [],
                $validated['product_ids'] ?? []
            )
        ))));

        if (empty($productIds)) {
            throw ValidationException::withMessages([
                'product_id' => 'Please select at least one product for the bundle deal.',
            ]);
        }

        $ownedCount = Product::where('seller_id', $seller->id)
            ->whereIn('id', $productIds)
            ->count();

        abort_unless($ownedCount === count($productIds), 403);

        $discountType = $validated['discount_type'] ?? 'percentage';
        $discountVal = (float) $validated['discount_value'];

        if ($discountType === 'percentage' && ($discountVal < 1 || $discountVal > 99)) {
            throw ValidationException::withMessages([
                'discount_value' => 'Bundle discount percentage must be between 1% and 99%.',
            ]);
        }

        SellerPromotion::create([
            'seller_id' => $seller->id,
            'name' => $validated['name'] ?? ("Any {$validated['min_quantity']} items for {$discountVal}% off"),
            'type' => 'bundle',
            'product_id' => $productIds[0] ?? null,
            'product_ids' => $productIds,
            'min_quantity' => (int) $validated['min_quantity'],
            'discount_type' => $discountType,
            'discount_value' => round($discountVal, 2),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ]);

        return back()->with('status', 'Bundle deal created successfully.');
    }

    /**
     * End a promotion immediately.
     */
    public function endNow(Request $request, SellerPromotion $promotion): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $promotion->seller_id === (int) $seller->id, 403);

        $promotion->update([
            'is_active' => false,
            'ended_at' => now(),
        ]);

        return back()->with('status', 'Promotion ended.');
    }

    /**
     * Delete a promotion.
     */
    public function destroy(Request $request, SellerPromotion $promotion): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $promotion->seller_id === (int) $seller->id, 403);

        $promotion->delete();

        return back()->with('status', 'Promotion removed.');
    }
}

