<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerFlashSaleController extends Controller
{
    /**
     * Create a flash sale in a time slot (SEL-FLASH-001 & SEL-FLASH-002).
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $this->normalizeRequest($request);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'flash_price' => ['required'],
            'flash_quantity' => ['required'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'slot' => ['nullable', 'string', 'max:40'],
        ]);

        $product = Product::where('id', $validated['product_id'])
            ->where('seller_id', $seller->id)
            ->first();

        abort_unless($product !== null, 403);

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The flash sale end time must be after the start time.',
            ]);
        }

        $flashPrice = $this->validateFlashPrice($request->input('flash_price'), $product);
        $flashQuantity = $this->validateFlashQuantity($request->input('flash_quantity'), $product);

        FlashSale::create([
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'flash_price' => $flashPrice,
            'flash_quantity' => $flashQuantity,
            'sold_quantity' => 0,
            'slot' => $validated['slot'] ?? ($startsAt->format('H:i') . '–' . $endsAt->format('H:i')),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return back()->with('status', 'Flash sale created successfully.');
    }

    /**
     * Update an existing flash sale.
     */
    public function update(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $flashSale->seller_id === (int) $seller->id, 403);

        $this->normalizeRequest($request);

        $product = $flashSale->product;
        abort_unless($product !== null, 404);

        $startsAt = $request->filled('starts_at')
            ? Carbon::parse($request->input('starts_at'))
            : $flashSale->starts_at;

        $endsAt = $request->filled('ends_at')
            ? Carbon::parse($request->input('ends_at'))
            : $flashSale->ends_at;

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The flash sale end time must be after the start time.',
            ]);
        }

        $flashPrice = $request->has('flash_price')
            ? $this->validateFlashPrice($request->input('flash_price'), $product)
            : (float) $flashSale->flash_price;

        $flashQuantity = $request->has('flash_quantity')
            ? $this->validateFlashQuantity($request->input('flash_quantity'), $product)
            : (int) $flashSale->flash_quantity;

        $flashSale->update([
            'flash_price' => $flashPrice,
            'flash_quantity' => $flashQuantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot' => $request->input('slot', $flashSale->slot),
        ]);

        return back()->with('status', 'Flash sale updated successfully.');
    }

    /**
     * End a flash sale immediately.
     */
    public function endNow(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $flashSale->seller_id === (int) $seller->id, 403);

        $flashSale->update([
            'ended_at' => now(),
        ]);

        return back()->with('status', 'Flash sale ended.');
    }

    /**
     * Delete a flash sale.
     */
    public function destroy(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $seller = $request->user();
        abort_unless((int) $flashSale->seller_id === (int) $seller->id, 403);

        $flashSale->delete();

        return back()->with('status', 'Flash sale removed.');
    }

    private function normalizeRequest(Request $request): void
    {
        if (! $request->has('flash_price')) {
            if ($request->has('price')) {
                $request->merge(['flash_price' => $request->input('price')]);
            } elseif ($request->has('value')) {
                $request->merge(['flash_price' => $request->input('value')]);
            }
        }

        if (! $request->has('flash_quantity') && $request->has('quantity')) {
            $request->merge(['flash_quantity' => $request->input('quantity')]);
        }

        // Parse slot like "12:00-14:00" or "12:00–14:00" if starts_at/ends_at are omitted
        if ((! $request->filled('starts_at') || ! $request->filled('ends_at')) && $request->filled('slot')) {
            $slotStr = trim((string) $request->input('slot'));
            $dateStr = $request->input('date', now()->toDateString());

            if (preg_match('/^(\d{1,2}:\d{2})\s*[-–—]\s*(\d{1,2}:\d{2})$/u', $slotStr, $m)) {
                $request->merge([
                    'starts_at' => Carbon::parse("{$dateStr} {$m[1]}:00")->toDateTimeString(),
                    'ends_at' => Carbon::parse("{$dateStr} {$m[2]}:00")->toDateTimeString(),
                ]);
            }
        }
    }

    private function validateFlashPrice(mixed $rawPrice, Product $product): float
    {
        $strVal = trim((string) $rawPrice);

        if (! is_numeric($strVal) || ! preg_match('/^-?\d+(\.\d{1,2})?$/', $strVal)) {
            throw ValidationException::withMessages([
                'flash_price' => 'Flash price must be a valid amount with up to 2 decimal places.',
            ]);
        }

        $price = (float) $strVal;
        $regularPrice = (float) $product->getRawOriginal('price');

        if ($price < 1.00 || $price >= $regularPrice) {
            throw ValidationException::withMessages([
                'flash_price' => 'Flash price must be at least ₱1.00 and strictly below the regular price (₱' . number_format($regularPrice, 2) . ').',
            ]);
        }

        return round($price, 2);
    }

    private function validateFlashQuantity(mixed $rawQuantity, Product $product): int
    {
        $strVal = trim((string) $rawQuantity);

        if (! preg_match('/^-?\d+$/', $strVal)) {
            throw ValidationException::withMessages([
                'flash_quantity' => 'Flash quantity must be a whole number.',
            ]);
        }

        $qty = (int) $strVal;
        $availableStock = (int) $product->stock;

        if ($qty < 1) {
            throw ValidationException::withMessages([
                'flash_quantity' => 'Flash quantity must be at least 1.',
            ]);
        }

        if ($qty > $availableStock) {
            throw ValidationException::withMessages([
                'flash_quantity' => "Flash quantity cannot exceed available product stock ({$availableStock}).",
            ]);
        }

        return $qty;
    }
}

