<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSale;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerSaleController extends Controller
{
    /**
     * Create a scheduled or immediate product sale (SEL-DISC-001 to SEL-DISC-008).
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $this->normalizeAliases($request);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
        ]);

        $product = Product::where('id', $validated['product_id'])
            ->where('seller_id', $seller->id)
            ->first();

        abort_unless($product !== null, 403);

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);

        // SEL-DISC-004: Reject start in the past
        if ($startsAt->lessThan(now()->subSeconds(2))) {
            throw ValidationException::withMessages([
                'starts_at' => 'The sale start date and time cannot be in the past.',
            ]);
        }

        // SEL-DISC-004: Reject end before start or start = end
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The sale end date and time must be after the start date and time.',
            ]);
        }

        $value = $this->validateDiscountValue(
            $validated['type'],
            $request->input('value'),
            $product
        );

        // SEL-DISC-007 / A28: Reject overlapping sales on the same product
        $this->assertNoOverlappingSale($product, $startsAt, $endsAt);

        ProductSale::create([
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'type' => $validated['type'],
            'value' => $value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return back()->with('status', 'Product sale scheduled successfully.');
    }

    /**
     * Edit an upcoming or running sale (SEL-DISC-009).
     */
    public function update(Request $request, ProductSale $sale): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $sale->seller_id === (int) $seller->id, 403);

        $this->normalizeAliases($request, $sale->type);

        $type = $request->input('type', $sale->type);
        $startsAtInput = $request->input('starts_at');
        $endsAtInput = $request->input('ends_at');

        $startsAt = $startsAtInput ? Carbon::parse($startsAtInput) : $sale->starts_at;
        $endsAt = $endsAtInput ? Carbon::parse($endsAtInput) : $sale->ends_at;

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The sale end date and time must be after the start date and time.',
            ]);
        }

        $product = $sale->product;
        abort_unless($product !== null, 404);

        $value = $request->has('value')
            ? $this->validateDiscountValue($type, $request->input('value'), $product)
            : (float) $sale->value;

        $this->assertNoOverlappingSale($product, $startsAt, $endsAt, $sale->id);

        $sale->update([
            'type' => $type,
            'value' => $value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return back()->with('status', 'Product sale updated successfully.');
    }

    /**
     * End a running sale immediately (SEL-DISC-010).
     */
    public function endNow(Request $request, ProductSale $sale): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $sale->seller_id === (int) $seller->id, 403);

        $sale->update([
            'ended_at' => now(),
        ]);

        return back()->with('status', 'Product sale ended.');
    }

    /**
     * Delete a product sale.
     */
    public function destroy(Request $request, ProductSale $sale): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $sale->seller_id === (int) $seller->id, 403);

        $sale->delete();

        return back()->with('status', 'Product sale removed.');
    }

    private function normalizeAliases(Request $request, ?string $defaultType = null): void
    {
        if (!$request->has('value')) {
            if ($request->has('discount_percentage')) {
                $request->merge([
                    'type' => $request->input('type', 'percentage'),
                    'value' => $request->input('discount_percentage'),
                ]);
            } elseif ($request->has('percentage')) {
                $request->merge([
                    'type' => $request->input('type', 'percentage'),
                    'value' => $request->input('percentage'),
                ]);
            } elseif ($request->has('sale_price')) {
                $request->merge([
                    'type' => $request->input('type', 'fixed'),
                    'value' => $request->input('sale_price'),
                ]);
            } elseif ($request->has('fixed_price')) {
                $request->merge([
                    'type' => $request->input('type', 'fixed'),
                    'value' => $request->input('fixed_price'),
                ]);
            }
        }

        if (!$request->has('type') && $defaultType) {
            $request->merge(['type' => $defaultType]);
        } elseif (!$request->has('type')) {
            $request->merge(['type' => 'percentage']);
        }
    }

    private function validateDiscountValue(string $type, mixed $rawValue, Product $product): float
    {
        $strValue = trim((string) $rawValue);

        if ($type === 'percentage') {
            // SEL-DISC-005: Only whole percentages from 1% to 99% are accepted
            if (!preg_match('/^-?\d+$/', $strValue)) {
                throw ValidationException::withMessages([
                    'value' => 'Discount percentage must be a whole number between 1% and 99%.',
                ]);
            }

            $intVal = (int) $strValue;
            if ($intVal < 1 || $intVal > 99) {
                throw ValidationException::withMessages([
                    'value' => 'Discount percentage must be between 1% and 99%.',
                ]);
            }

            return (float) $intVal;
        }

        // SEL-DISC-006: Fixed sale price from ₱1.00 up to below original price
        if (!is_numeric($strValue) || !preg_match('/^-?\d+(\.\d{1,2})?$/', $strValue)) {
            throw ValidationException::withMessages([
                'value' => 'Fixed sale price must be a valid amount with up to 2 decimal places.',
            ]);
        }

        $floatVal = (float) $strValue;
        $regularPrice = (float) $product->getRawOriginal('price');

        if ($floatVal < 1.00 || $floatVal >= $regularPrice) {
            throw ValidationException::withMessages([
                'value' => 'Fixed sale price must be at least ₱1.00 and strictly below the original price (₱' . number_format($regularPrice, 2) . ').',
            ]);
        }

        return round($floatVal, 2);
    }

    private function assertNoOverlappingSale(
        Product $product,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreId = null
    ): void {
        $overlapping = ProductSale::query()
            ->where('product_id', $product->id)
            ->whereNull('ended_at')
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlapping) {
            $message = "{$product->name} already has a sale in that period.";
            throw ValidationException::withMessages([
                'product_id' => $message,
                'starts_at' => $message,
            ]);
        }
    }
}

