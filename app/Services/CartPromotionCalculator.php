<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\FlashSale;
use App\Models\SellerPromotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CartPromotionCalculator
{
    /**
     * Evaluate flash sale splits, bundle deals (SEL-FLASH-007), and Buy X Get Y (SEL-FLASH-008)
     * for a collection of CartItem models.
     */
    public function evaluate(Collection $cartItems): array
    {
        if ($cartItems->isEmpty()) {
            return [
                'items' => collect(),
                'checkout_lines' => collect(),
                'raw_subtotal' => 0.0,
                'bundle_discount' => 0.0,
                'buy_x_get_y_discount' => 0.0,
                'promotion_discount' => 0.0,
                'subtotal_after_promotions' => 0.0,
            ];
        }

        // Track remaining flash units per product across the cart
        $flashSalesByProduct = [];
        $flashRemainingByProduct = [];

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (! $product || isset($flashSalesByProduct[$product->id])) {
                continue;
            }
            $flash = $product->activeFlashSale();
            $flashSalesByProduct[$product->id] = $flash;
            $flashRemainingByProduct[$product->id] = $flash ? $flash->remainingQuantity() : 0;
        }

        $checkoutLines = collect();
        $itemPricingMap = [];
        $rawSubtotal = 0.0;

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $qty = max(1, (int) $item->quantity);
            $varPrice = $item->variant ? (float) $item->variant->getRawOriginal('price') : 0.0;
            $prodPrice = (float) $product->getRawOriginal('price');
            $baseRegular = $varPrice > 0 ? $varPrice : $prodPrice;

            $nonFlashPrice = $product->nonFlashPriceFor($baseRegular);
            $flash = $flashSalesByProduct[$product->id] ?? null;
            $remainingFlash = $flashRemainingByProduct[$product->id] ?? 0;

            $flashUnits = 0;
            if ($flash && $remainingFlash > 0) {
                $flashUnits = min($qty, $remainingFlash);
                $flashRemainingByProduct[$product->id] -= $flashUnits;
            }
            $normalUnits = $qty - $flashUnits;
            $flashPrice = $flash ? round((float) $flash->flash_price, 2) : $nonFlashPrice;

            $itemLines = [];
            if ($flashUnits > 0) {
                $lineTotal = round($flashPrice * $flashUnits, 2);
                $rawSubtotal += $lineTotal;
                $lineData = $this->buildLineData($item, $flashUnits, $flashPrice, $baseRegular, true, $flash->id);
                $checkoutLines->push($lineData);
                $itemLines[] = $lineData;
            }

            if ($normalUnits > 0) {
                $lineTotal = round($nonFlashPrice * $normalUnits, 2);
                $rawSubtotal += $lineTotal;
                $lineData = $this->buildLineData($item, $normalUnits, $nonFlashPrice, $baseRegular, false, null);
                $checkoutLines->push($lineData);
                $itemLines[] = $lineData;
            }

            $itemLineSubtotal = array_sum(array_column($itemLines, 'line_total_float'));
            $avgUnitPrice = $qty > 0 ? round($itemLineSubtotal / $qty, 2) : $nonFlashPrice;

            $itemPricingMap[$item->id] = [
                'base_unit_price' => $avgUnitPrice,
                'regular_unit_price' => $baseRegular,
                'line_subtotal' => round($itemLineSubtotal, 2),
                'effective_unit_price' => $avgUnitPrice,
                'effective_line_total' => round($itemLineSubtotal, 2),
                'is_free_gift' => false,
                'free_quantity' => 0,
                'bundle_applied' => false,
                'bundle_discount' => 0.0,
                'buy_x_get_y_discount' => 0.0,
                'lines' => $itemLines,
            ];
        }

        $bundleDiscountTotal = 0.0;
        $buyXGetYDiscountTotal = 0.0;

        if (Schema::hasTable('seller_promotions')) {
            $sellerIds = $cartItems
                ->map(fn ($item) => $item->product?->seller_id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $promotions = SellerPromotion::query()
                ->whereIn('seller_id', $sellerIds)
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->get()
                ->filter(fn (SellerPromotion $promo) => $promo->isActiveNow());

            foreach ($promotions as $promo) {
                if ($promo->type === 'bundle') {
                    // SEL-FLASH-007: Bundle deal item counting across all variants of eligible product(s)
                    $matchingItems = $cartItems->filter(function ($item) use ($promo) {
                        return $item->product
                            && (int) $item->product->seller_id === (int) $promo->seller_id
                            && $promo->appliesToBundleProduct((int) $item->product_id);
                    });

                    $totalBundleQty = (int) $matchingItems->sum('quantity');
                    $minQty = max(1, (int) ($promo->min_quantity ?? 2));

                    if ($totalBundleQty >= $minQty) {
                        $eligibleMerchandiseTotal = 0.0;
                        foreach ($matchingItems as $mItem) {
                            $eligibleMerchandiseTotal += $itemPricingMap[$mItem->id]['line_subtotal'] ?? 0.0;
                        }

                        $discountForBundle = $promo->discount_type === 'fixed'
                            ? round(min($eligibleMerchandiseTotal, (float) $promo->discount_value), 2)
                            : round($eligibleMerchandiseTotal * ((float) $promo->discount_value / 100.0), 2);

                        if ($discountForBundle > 0) {
                            $bundleDiscountTotal = round($bundleDiscountTotal + $discountForBundle, 2);

                            foreach ($matchingItems as $mItem) {
                                $share = $eligibleMerchandiseTotal > 0
                                    ? round($discountForBundle * ($itemPricingMap[$mItem->id]['line_subtotal'] / $eligibleMerchandiseTotal), 2)
                                    : 0.0;
                                $itemPricingMap[$mItem->id]['bundle_applied'] = true;
                                $itemPricingMap[$mItem->id]['bundle_discount'] = round(
                                    ($itemPricingMap[$mItem->id]['bundle_discount'] ?? 0.0) + $share,
                                    2
                                );
                            }
                        }
                    }
                } elseif ($promo->type === 'buy_x_get_y') {
                    // SEL-FLASH-008: Buy X of buy_product_id, Get Y of get_product_id free/discounted
                    $buyProductId = (int) $promo->buy_product_id;
                    $getProductId = (int) $promo->get_product_id;
                    $buyRequired = max(1, (int) ($promo->buy_quantity ?? 1));
                    $getQtyPerSet = max(1, (int) ($promo->get_quantity ?? 1));
                    $discountPct = min(100.0, max(0.0, (float) ($promo->get_discount_percentage ?? 100.0)));

                    $buyQtyInCart = (int) $cartItems
                        ->filter(fn ($i) => (int) $i->product_id === $buyProductId)
                        ->sum('quantity');

                    if ($buyQtyInCart >= $buyRequired) {
                        $sets = intdiv($buyQtyInCart, $buyRequired);
                        $freeUnitsRemaining = $sets * $getQtyPerSet;

                        $getItems = $cartItems->filter(fn ($i) => (int) $i->product_id === $getProductId);

                        foreach ($getItems as $gItem) {
                            if ($freeUnitsRemaining <= 0) {
                                break;
                            }

                            $gQty = max(1, (int) $gItem->quantity);
                            $freeForThisItem = min($gQty, $freeUnitsRemaining);
                            $freeUnitsRemaining -= $freeForThisItem;

                            $unitPrice = $itemPricingMap[$gItem->id]['base_unit_price'] ?? 0.0;
                            $discountPerUnit = round($unitPrice * ($discountPct / 100.0), 2);
                            $itemDiscount = round($discountPerUnit * $freeForThisItem, 2);

                            $buyXGetYDiscountTotal = round($buyXGetYDiscountTotal + $itemDiscount, 2);

                            $newLineTotal = max(0.0, round(($itemPricingMap[$gItem->id]['line_subtotal'] ?? 0.0) - $itemDiscount, 2));
                            $newUnitPrice = $gQty > 0 ? round($newLineTotal / $gQty, 2) : 0.0;

                            $itemPricingMap[$gItem->id]['effective_unit_price'] = $newUnitPrice;
                            $itemPricingMap[$gItem->id]['effective_line_total'] = $newLineTotal;
                            $itemPricingMap[$gItem->id]['is_free_gift'] = ($newUnitPrice <= 0.0);
                            $itemPricingMap[$gItem->id]['free_quantity'] = $freeForThisItem;
                            $itemPricingMap[$gItem->id]['buy_x_get_y_discount'] = $itemDiscount;

                            // Update corresponding checkout line(s) for this item
                            $checkoutLines = $checkoutLines->map(function (array $line) use ($gItem, $newUnitPrice, $newLineTotal) {
                                if ((int) ($line['cart_item_id'] ?? 0) === (int) $gItem->id) {
                                    $line['price'] = number_format($newUnitPrice, 2, '.', '');
                                    $line['unit_price'] = number_format($newUnitPrice, 2, '.', '');
                                    $line['effective_price'] = number_format($newUnitPrice, 2, '.', '');
                                    $line['line_total'] = number_format($newLineTotal, 2, '.', '');
                                    $line['line_total_float'] = $newLineTotal;
                                    $line['is_free_gift'] = ($newUnitPrice <= 0.0);
                                }
                                return $line;
                            });
                        }
                    }
                }
            }
        }

        // Attach computed attributes to each CartItem for Inertia / JSON serialization
        foreach ($cartItems as $item) {
            $meta = $itemPricingMap[$item->id] ?? null;
            if (! $meta) {
                continue;
            }

            $effectiveUnitStr = number_format($meta['effective_unit_price'], 2, '.', '');
            $item->setAttribute('unit_price', number_format($meta['base_unit_price'], 2, '.', ''));
            $item->setAttribute('price', $effectiveUnitStr);
            $item->setAttribute('effective_price', $effectiveUnitStr);
            $item->setAttribute('line_subtotal', number_format($meta['line_subtotal'], 2, '.', ''));
            $item->setAttribute('effective_line_total', number_format($meta['effective_line_total'], 2, '.', ''));
            $item->setAttribute('is_free_gift', $meta['is_free_gift']);
            $item->setAttribute('free_quantity', $meta['free_quantity']);
            $item->setAttribute('bundle_applied', $meta['bundle_applied']);
            $item->setAttribute('bundle_discount', number_format($meta['bundle_discount'], 2, '.', ''));
            $item->setAttribute('buy_x_get_y_discount', number_format($meta['buy_x_get_y_discount'], 2, '.', ''));
        }

        $rawSubtotal = round($rawSubtotal, 2);
        $promotionDiscount = round($bundleDiscountTotal + $buyXGetYDiscountTotal, 2);
        $subtotalAfterPromotions = max(0.0, round($rawSubtotal - $promotionDiscount, 2));

        return [
            'items' => $cartItems,
            'checkout_lines' => $checkoutLines->values(),
            'item_pricing_map' => $itemPricingMap,
            'raw_subtotal' => $rawSubtotal,
            'bundle_discount' => $bundleDiscountTotal,
            'buy_x_get_y_discount' => $buyXGetYDiscountTotal,
            'promotion_discount' => $promotionDiscount,
            'subtotal_after_promotions' => $subtotalAfterPromotions,
        ];
    }

    private function buildLineData(
        CartItem $item,
        int $quantity,
        float $unitPrice,
        float $regularPrice,
        bool $isFlashSale,
        ?int $flashSaleId
    ): array {
        $lineTotal = round($unitPrice * $quantity, 2);
        $unitPriceStr = number_format($unitPrice, 2, '.', '');

        return [
            'id' => $item->id,
            'cart_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'variant_label' => $item->variant ? $item->variant->label() : $item->variant_label,
            'quantity' => $quantity,
            'price' => $unitPriceStr,
            'unit_price' => $unitPriceStr,
            'effective_price' => $unitPriceStr,
            'regular_price' => number_format($regularPrice, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
            'line_total_float' => $lineTotal,
            'is_flash_sale' => $isFlashSale,
            'flash_sale_id' => $flashSaleId,
            'is_free_gift' => false,
            'is_unavailable' => $item->is_unavailable,
            'unavailable_reason' => $item->unavailable_reason,
            'stock_warning' => $item->stock_warning,
            'product' => $item->product ? $item->product->toArray() : null,
            'variant' => $item->variant ? $item->variant->toArray() : null,
        ];
    }
}

