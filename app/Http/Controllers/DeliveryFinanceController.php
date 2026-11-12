<?php

namespace App\Http\Controllers;

use App\Models\RiderDeliveryEarning;
use App\Models\SellerDeliveryCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryFinanceController extends Controller
{
    public function rider(Request $request): JsonResponse
    {
        return response()->json($this->listing($request, RiderDeliveryEarning::where('rider_id', $request->user()->rider->id), false));
    }

    public function seller(Request $request): JsonResponse
    {
        return response()->json($this->listing($request, SellerDeliveryCharge::where('seller_id', $request->user()->id), true));
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['charges' => $this->listing($request, SellerDeliveryCharge::query(), true),
            'earnings' => $this->listing($request, RiderDeliveryEarning::query(), false)]);
    }

    private function listing(Request $request, Builder $query, bool $charges): array
    {
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $time = $charges ? 'recognized_at' : 'earned_at';
        if (! empty($input['from'])) {
            $query->where($time, '>=', \Carbon\CarbonImmutable::parse($input['from'])->startOfDay());
        }
        if (! empty($input['to'])) {
            $query->where($time, '<=', \Carbon\CarbonImmutable::parse($input['to'])->endOfDay());
        }
        $status = $charges ? 'collection_status' : 'payout_status';
        $settled = $charges ? 'collected' : 'paid';
        $sum = function (Builder $builder, string $column = 'amount'): string {
            $cents = (int) $builder->selectRaw('COALESCE(SUM('.$column.' * 100), 0) as cents')->value('cents');

            return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
        };
        $summary = ['currency' => 'PHP', 'total' => $sum(clone $query),
            $settled => $sum((clone $query)->where($status, $settled)),
            'outstanding' => $sum((clone $query)->where($status, '!=', $settled))];
        if ($charges) {
            $summary['logistics_earned'] = $sum(clone $query, 'logistics_amount');
            $summary['rider_allocated'] = $sum(clone $query, 'rider_amount');
        }
        $columns = $charges
            ? ['id', 'seller_shipment_id', 'currency', 'amount', 'logistics_amount', 'rider_amount', 'recognized_at', 'collection_status', 'collected_at']
            : ['id', 'seller_delivery_charge_id', 'amount', 'earned_at', 'payout_status', 'paid_at'];
        if (! $charges) {
            $query->with('charge:id,seller_shipment_id,currency');
        }
        $rows = $query->orderByDesc($time)->orderByDesc('id')->paginate($input['per_page'] ?? 20, $columns);

        return ['data' => $rows->items(), 'summary' => $summary,
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total(), 'per_page' => $rows->perPage()]];
    }

    public function collect(Request $request, int $id): JsonResponse
    {
        return $this->settle($request, $id, true);
    }

    public function payout(Request $request, int $id): JsonResponse
    {
        return $this->settle($request, $id, false);
    }

    private function settle(Request $request, int $id, bool $collection): JsonResponse
    {
        $input = $request->validate(['request_key' => ['required', 'uuid'], 'reference' => ['required', 'string', 'max:150'],
            'amount' => ['prohibited'], 'status' => ['prohibited'], 'recorded_by' => ['prohibited']]);
        $model = $collection ? SellerDeliveryCharge::class : RiderDeliveryEarning::class;
        try {
            $row = DB::transaction(function () use ($request, $id, $input, $collection, $model) {
                $row = $model::whereKey($id)->lockForUpdate()->firstOrFail();
                $status = $collection ? 'collection_status' : 'payout_status';
                $settled = $collection ? 'collected' : 'paid';
                if ($row->$status === $settled) {
                    abort_unless($row->settlement_key === $input['request_key'] && $row->manual_reference === $input['reference'],
                        409, 'This entry already has a recorded full settlement.');

                    return $row;
                }
                abort_if($model::where('settlement_key', $input['request_key'])->exists(), 409, 'This request key is already used.');
                $row->update([$status => $settled, $collection ? 'collected_at' : 'paid_at' => now(),
                    'recorded_by' => $request->user()->id, 'manual_reference' => $input['reference'], 'settlement_key' => $input['request_key']]);

                return $row;
            });
        } catch (UniqueConstraintViolationException $error) {
            abort(409, 'This request key is already used.');
        }

        return response()->json(['data' => $row]);
    }
}
