<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Rider, RiderPayBatch, RiderPayDestination, RiderPayEarning, RiderPayTrip};
use App\Services\RiderJobPay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;

class RiderPayController extends Controller
{
    public function __construct(private RiderJobPay $pay) {}

    private function result(callable $operation, int $status = 200): JsonResponse
    {
        try { return response()->json(['data' => $operation()], $status)->header('Cache-Control', 'private, no-store'); }
        catch (UniqueConstraintViolationException $error) { abort(409, 'This earning, trip or payment reference already exists. Refresh before retrying.'); }
    }

    private function scope(Request $request): array
    {
        $this->pay->requireStorage();
        if ($request->is('api/rider/*')) {
            $rider = $this->pay->rider($request->user());
            return ['rider_id', (int) $rider->id];
        }
        return ['sorting_center_id', $this->pay->owner($request->user())];
    }

    private function summary(string $column, int $id): array
    {
        $earnings = RiderPayEarning::where($column, $id)->where('status', 'earned');
        $batches = RiderPayBatch::where($column, $id);
        return ['currency' => 'PHP', 'earned' => $this->pay->money((int) (clone $earnings)->sum('amount_cents')),
            'unpaid' => $this->pay->money((int) (clone $earnings)->whereNull('batch_id')->sum('amount_cents')),
            'processing' => $this->pay->money((int) (clone $batches)->where('status', 'prepared')->sum('amount_cents')),
            'paid' => $this->pay->money((int) (clone $batches)->where('status', 'paid')->sum('amount_cents'))];
    }

    private function earning(RiderPayEarning $row): array
    {
        return ['id' => $row->id, 'rider_id' => $row->rider_id, 'leg' => $row->leg,
            'reference' => $row->snapshot['reference'], 'amount' => $this->pay->money($row->amount_cents),
            'status' => $row->status, 'batch_id' => $row->batch_id, 'earned_at' => $row->earned_at?->toISOString(),
            'created_at' => $row->created_at->toISOString()];
    }

    private function batch(RiderPayBatch $batch, bool $details = false): array
    {
        $result = $batch->toArray();
        unset($result['amount_cents']);
        $result['amount'] = $this->pay->money($batch->amount_cents);
        $result['currency'] = 'PHP';
        $result['destination'] = RiderPayDestination::findOrFail($batch->destination_id)->only(['id', 'method', 'label']);
        $result['has_proof'] = (bool) $batch->proof_path;
        if ($details) {
            $result['instructions'] = RiderPayDestination::findOrFail($batch->destination_id)->instructions;
            $ids = \Illuminate\Support\Facades\DB::table('rider_pay_batch_items')->where('batch_id', $batch->id)->pluck('earning_id');
            $result['jobs'] = RiderPayEarning::whereIn('id', $ids)->orderBy('id')->get()->map(fn ($row) => $this->earning($row));
        }
        return $result;
    }

    public function index(Request $request): JsonResponse
    {
        [$column, $id] = $this->scope($request);
        $request->validate(['earnings_page' => ['sometimes', 'integer', 'min:1'], 'payouts_page' => ['sometimes', 'integer', 'min:1']]);
        return $this->result(function () use ($column, $id, $request) {
            $jobs = RiderPayEarning::where($column, $id)->orderByDesc('id')->paginate(20, ['*'], 'earnings_page');
            $jobs->through(fn ($row) => $this->earning($row));
            $batches = RiderPayBatch::where($column, $id)->orderByDesc('id')->paginate(15, ['*'], 'payouts_page');
            $batches->through(fn ($row) => $this->batch($row));
            $result = ['summary' => $this->summary($column, $id), 'earnings' => $jobs, 'payouts' => $batches];
            if ($column === 'sorting_center_id') {
                $result['settings'] = $this->pay->settings($id);
                $result['riders'] = Rider::where('sorting_center_id', $id)->operationallyEligible()->orderBy('name')->get(['id', 'name']);
            } else {
                $result['destinations'] = RiderPayDestination::where('rider_id', $id)->orderByDesc('id')->get();
            }
            return $result;
        });
    }

    public function settings(Request $request): JsonResponse
    {
        $input = $request->validate(['enabled' => ['required', 'boolean'], 'revision' => ['required', 'integer', 'min:0'],
            'pickup' => ['nullable', 'string', 'max:20'], 'delivery' => ['nullable', 'string', 'max:20'], 'transfer' => ['nullable', 'string', 'max:20']]);
        return $this->result(fn () => $this->pay->configure($request->user(), $input));
    }

    public function destination(Request $request): JsonResponse
    {
        $input = $request->validate(['method' => ['required', 'in:cash,gcash,bank'], 'account_name' => ['required', 'string', 'max:60'],
            'account_number' => ['nullable', 'string', 'max:40'], 'institution' => ['nullable', 'string', 'max:80']]);
        return $this->result(fn () => $this->pay->destination($request->user(), $input), 201);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $this->pay->requireStorage(); $center = $this->pay->owner($request->user());
        Rider::where('sorting_center_id', $center)->operationallyEligible()->findOrFail($id);
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        return $this->result(function () use ($center, $id, $input) {
            $query = RiderPayEarning::where('sorting_center_id', $center)->where('rider_id', $id)->where('status', 'earned')->whereNull('batch_id')
                ->when(!empty($input['from']), fn ($q) => $q->where('earned_at', '>=', \Carbon\CarbonImmutable::parse($input['from'], 'Asia/Manila')->startOfDay()->utc()))
                ->when(!empty($input['to']), fn ($q) => $q->where('earned_at', '<=', \Carbon\CarbonImmutable::parse($input['to'], 'Asia/Manila')->endOfDay()->utc()));
            $count = (clone $query)->count();
            $rows = $query->orderBy('id')->limit(500)->get();
            return ['jobs' => $rows->map(fn ($row) => $this->earning($row)), 'amount' => $this->pay->money((int) $rows->sum('amount_cents')),
                'has_more' => $count > 500, 'destinations' => RiderPayDestination::where('rider_id', $id)->orderByDesc('id')->get()];
        });
    }

    public function prepare(Request $request): JsonResponse
    {
        $input = $request->validate(['rider_id' => ['required', 'integer', 'min:1'], 'destination_id' => ['required', 'integer', 'min:1'],
            'earning_ids' => ['required', 'array', 'min:1', 'max:500'], 'earning_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'expected_amount' => ['required', 'string', 'max:20'], 'request_key' => ['required', 'uuid'], 'reviewed' => ['required', 'accepted']]);
        return $this->result(fn () => $this->batch($this->pay->prepare($request->user(), $input), true), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        [$column, $scope] = $this->scope($request);
        return $this->result(fn () => $this->batch(RiderPayBatch::where($column, $scope)->findOrFail($id), true));
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $input = $request->validate(['expected_amount' => ['required', 'string', 'max:20'], 'payment_key' => ['required', 'uuid'],
            'reference' => ['required', 'string', 'regex:/\A[A-Za-z0-9][A-Za-z0-9 _\/-]{2,119}\z/'],
            'actually_paid' => ['required', 'accepted'], 'paid_from_payroll' => ['required', 'accepted'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'], 'status' => ['prohibited'], 'amount' => ['prohibited']]);
        return $this->result(fn () => $this->batch($this->pay->pay($request->user(), $id, $input, $request->file('proof'))));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->pay->requireStorage();
        $input = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500'], 'not_sent' => ['required', 'accepted']]);
        return $this->result(fn () => $this->batch($this->pay->cancel($request->user(), $id, $input['reason'])));
    }

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $this->pay->requireStorage();
        $request->validate(['received' => ['required', 'accepted']]);
        return $this->result(fn () => $this->batch($this->pay->acknowledge($request->user(), $id)));
    }

    public function proof(Request $request, int $id)
    {
        [$column, $scope] = $this->scope($request);
        $batch = RiderPayBatch::where($column, $scope)->findOrFail($id);
        abort_unless($batch->proof_path && str_starts_with($batch->proof_path, 'rider-payout-proofs/') && Storage::disk('local')->exists($batch->proof_path), 404);
        return Storage::disk('local')->response($batch->proof_path, 'payout-'.$id.'.'.pathinfo($batch->proof_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    public function trips(Request $request): JsonResponse
    {
        $this->pay->requireStorage(); $center = $this->pay->owner($request->user());
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        return $this->result(fn () => RiderPayTrip::where('sorting_center_id', $center)->orderByDesc('id')->paginate(20));
    }

    public function dispatchTrip(Request $request): JsonResponse
    {
        $input = $request->validate(['rider_id' => ['required', 'integer', 'min:1'], 'hub_id' => ['required', 'integer', 'min:1'],
            'parcel_ids' => ['required', 'array', 'min:1', 'max:200'], 'parcel_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'transport_reference' => ['required', 'string', 'min:3', 'max:100'], 'request_key' => ['required', 'uuid'], 'physical_confirmation' => ['required', 'accepted']]);
        return $this->result(fn () => $this->pay->dispatchTrip($request->user(), $input), 201);
    }
}
