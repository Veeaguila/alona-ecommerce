<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Profitability
{
    public function validateOrder(Order $order, $items, int $shipping): array
    {
        $warnings = [];
        $rule = DB::table('payment_profitability_rules')->where('shipping_method', $order->shipping_method)->first();
        if ($rule) {
            if (! $rule->available) $this->block('This delivery option is currently unavailable.');
            foreach (json_decode($rule->blocked_zones ?? '[]', true) as $zone) {
                if ($zone !== '' && str_contains(mb_strtolower($order->shipping_address), mb_strtolower($zone))) {
                    $this->block('This delivery option is unavailable for the selected zone.');
                }
            }
            $sellerCount = max(1, $items->pluck('product.seller_id')->unique()->count());
            foreach (Money::split($shipping, array_fill(0, $sellerCount, 1)) as $fee) {
                $parts = Money::split($fee, ['rider' => 70, 'logistics' => 20, 'platform' => 10]);
                if ($parts['logistics'] < $rule->logistics_cost_cents + $rule->logistics_margin_cents
                    || $parts['rider'] < $rule->rider_cost_cents + $rule->rider_minimum_cents) {
                    $message = 'Shipping does not meet configured Logistics or Rider cost and minimum earnings requirements.';
                    if ($rule->block_shortfall) $this->block($message);
                    $warnings[] = $message;
                }
            }
        }
        foreach ($items as $item) {
            $cost = DB::table('payment_product_costs')->where('product_id', $item->product_id)->where('seller_id', $item->product->seller_id)->first();
            if (! $cost) continue;
            $gross = Money::cents($item->price) * $item->quantity;
            if ($gross - Money::percent($gross, 10) < ($cost->cost_cents + $cost->minimum_margin_cents) * $item->quantity) {
                $message = 'Product '.$item->product_name.' does not cover declared cost and margin after the 10% commission.';
                if ($cost->block_shortfall) $this->block($message);
                $warnings[] = $message;
            }
        }
        return array_values(array_unique($warnings));
    }

    private function block(string $message): never
    {
        throw ValidationException::withMessages(['shipping_method' => $message]);
    }
}
