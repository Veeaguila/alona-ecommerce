<?php

namespace App\Http\Controllers;

use App\Models\PaymentDistribution;
use App\Services\Payments\{DistributionLedger, Money};
use Illuminate\Http\Request;

/** Internal adapter contract: the provider integration verifies upstream settlement before signing. */
class PaymentSettlementWebhookController extends Controller
{
    public function __invoke(Request $request, DistributionLedger $ledger)
    {
        $secret = config('distribution.webhook_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32 && config('distribution.merchant'), 503, 'Verified settlement adapter is not configured.');
        $timestamp = $request->header('X-Alona-Timestamp', '');
        abort_unless(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 401);
        $signature = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        abort_unless(hash_equals($signature, $request->header('X-Alona-Signature', '')), 401);
        $data = $request->validate([
            'event_id' => 'required|string|max:100', 'merchant' => 'required|string', 'order_id' => 'required|integer',
            'currency' => 'required|in:PHP', 'status' => 'required|in:captured,settled', 'amount' => 'required|string',
            'gateway_fee' => 'required|string', 'reference' => 'required|string|min:3|max:180', 'account' => 'required|string|max:150',
        ]);
        abort_unless(hash_equals((string) config('distribution.merchant'), $data['merchant']), 401);
        abort_unless($ledger->installed(), 503);
        $distribution = PaymentDistribution::where('order_id', $data['order_id'])->firstOrFail();
        abort_unless(Money::cents($data['amount']) + Money::cents($data['gateway_fee']) === (int) $distribution->buyer_cents, 422, 'Settlement amount does not match the order.');
        if ($data['status'] === 'captured') {
            abort_unless(Money::cents($data['gateway_fee']) === 0, 422, 'Capture records the gross buyer amount; fees are recorded at settlement.');
            $ledger->capture($distribution->id, 'adapter:'.$data['event_id'], Money::cents($data['amount']), $data['reference']);
            return response()->json(['received' => true]);
        }
        $ledger->recordReceipt($distribution->id, ['request_key' => 'adapter:'.$data['event_id'], 'kind' => 'online',
            'amount' => $data['amount'], 'gateway_fee' => $data['gateway_fee'], 'fee_bearer' => 'platform',
            'account' => $data['account'], 'reference' => $data['reference'], 'evidence' => 'Verified adapter event '.$data['event_id']]);
        return response()->json(['received' => true]);
    }
}
