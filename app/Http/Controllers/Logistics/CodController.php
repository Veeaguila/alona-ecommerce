<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{CodCollection, CodDiscrepancy, CodRemittance, LogisticsAuditEvent, Parcel, Rider, SortingCenter};
use App\Services\{CodLedger, CodReport, LogisticsReportExport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class CodController extends Controller
{
    private function cashRules(bool $zero = false): array
    {
        return ['required', 'numeric', $zero ? 'min:0' : 'min:0.01', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'];
    }
    private function evidenceRules(): array { return ['required', 'file', 'image', 'max:20480', 'dimensions:max_width=6000,max_height=6000']; }
    private function rider(Request $request): Rider
    {
        abort_unless($request->user()->usertype === 'rider' && $request->user()->rider, 403);
        return $request->user()->rider;
    }
    public function mine(Request $request, CodLedger $ledger)
    {
        $rider = $this->rider($request); $ledger->requireStorage();
        $centerIds = CodCollection::where('rider_id', $rider->id)->distinct()->pluck('sorting_center_id');
        $balances = $centerIds->map(function ($id) use ($rider, $ledger) {
            return ['sorting_center_id' => $id, 'name' => SortingCenter::find($id)?->business_name,
                ...array_map($ledger->money(...), $ledger->balance($rider->id, $id))];
        });
        $candidates = Parcel::where('assigned_rider_id', $rider->id)->whereIn('status', ['OUT_FOR_DELIVERY', 'DELIVERED'])
            ->whereHas('orderItem.order', fn ($query) => $query->whereIn('payment_method', ['cod', 'cash_on_delivery']))
            ->whereDoesntHave('codCollections')->orderByDesc('id')->limit(100)->get(['id', 'order_id', 'status']);
        return response()->json(['data' => ['balances' => $balances, 'delivery_candidates' => $candidates,
            'collections' => CodCollection::where('rider_id', $rider->id)->with('parcel:id,order_id')->latest('id')->limit(100)->get(),
            'remittances' => CodRemittance::where('rider_id', $rider->id)->with('discrepancy')->latest('id')->limit(100)->get()]]);
    }
    public function collect(Request $request, int $id, CodLedger $ledger)
    {
        $input = $request->validate(['request_key' => ['required', 'uuid'], 'amount' => $this->cashRules(), 'file' => $this->evidenceRules()]);
        return response()->json(['message' => 'Actual COD collection recorded. This does not confirm remittance or delivery.',
            'data' => $ledger->collect($request->user(), $id, $input, $request->file('file'))], 201);
    }
    public function submit(Request $request, CodLedger $ledger)
    {
        $input = $request->validate(['request_key' => ['required', 'uuid'], 'amount' => $this->cashRules(),
            'sorting_center_id' => ['required', 'integer', 'min:1'], 'file' => $this->evidenceRules()]);
        return response()->json(['message' => 'Cash submitted for Logistics verification. It is not yet counted as received.',
            'data' => $ledger->submit($request->user(), $input, $request->file('file'))], 201);
    }
    public function confirm(Request $request, int $id, CodLedger $ledger)
    {
        $input = $request->validate(['amount' => $this->cashRules(true), 'file' => $this->evidenceRules(),
            'declare_shortfall' => ['sometimes', 'boolean'], 'reason' => ['nullable', 'string', 'min:10', 'max:2000']]);
        return response()->json(['message' => 'Actual cash receipt confirmed. Any recorded discrepancy awaits Admin approval.',
            'data' => $ledger->confirm($request->user(), $id, $input, $request->file('file'))]);
    }
    public function report(Request $request, CodLedger $ledger, CodReport $report)
    {
        $range = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1']]);
        $ledger->requireStorage(); $centerId = (int) $request->user()->sortingCenter->id;
        return DB::transaction(function () use ($centerId, $range, $ledger, $report) {
            $page = $report->query($centerId, $range)->orderByDesc('recorded_at')->orderBy('record_type')->orderByDesc('record_id')->paginate(25);
            $riderIds = CodCollection::where('sorting_center_id', $centerId)->distinct()->pluck('rider_id');
            $balances = $riderIds->map(fn ($id) => ['rider_id' => $id, 'name' => Rider::find($id)?->name, ...array_map($ledger->money(...), $ledger->balance($id, $centerId))]);
            return response()->json(['data' => ['records' => array_map($report->row(...), $page->items()), 'totals' => $report->totals($centerId, $range),
                'balances' => $balances, 'pending_remittances' => CodRemittance::where('sorting_center_id', $centerId)->where('status', 'submitted')->with('rider:id,name')->orderBy('id')->limit(100)->get(),
                'audits' => LogisticsAuditEvent::where('sorting_center_id', $centerId)->where('action', 'like', 'cod_%')->with('actor:id,name')->latest('id')->limit(100)->get(),
                'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]]);
        });
    }
    public function export(Request $request, CodLedger $ledger, CodReport $report)
    {
        $range = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'], 'format' => ['required', 'in:csv,xlsx']]);
        $ledger->requireStorage();
        if ($range['format'] === 'xlsx') abort_unless(class_exists(\ZipArchive::class) && class_exists(\XMLWriter::class), 503, 'XLSX export is unavailable.');
        $records = $report->query((int) $request->user()->sortingCenter->id, $range)->orderByDesc('recorded_at')->orderBy('record_type')->orderByDesc('record_id')->lazy(500)->map($report->row(...));
        $type = $range['format'] === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        return response()->streamDownload(function () use ($records, $range) {
            $output = fopen('php://output', 'wb');
            try { app(LogisticsReportExport::class)->write($output, $records, $range['format'], CodReport::COLUMNS, 'COD Cash'); }
            finally { fclose($output); }
        }, 'cod-'.$range['from'].'-to-'.$range['to'].'.'.$range['format'], ['Content-Type' => $type, 'Cache-Control' => 'private, no-store']);
    }
    public function disputes(Request $request, CodLedger $ledger)
    {
        $ledger->requireStorage();
        return response()->json(['data' => CodDiscrepancy::with('remittance.rider:id,name')->latest('id')->paginate(25)]);
    }
    public function approve(Request $request, int $id, CodLedger $ledger)
    {
        $input = $request->validate(['resolution' => ['required', 'string', 'min:10', 'max:2000']]);
        return response()->json(['message' => 'Resolution approved and audited. Recorded cash amounts and payouts are unchanged.',
            'data' => $ledger->approve($request->user(), $id, $input['resolution'])]);
    }
    public function evidence(Request $request, string $kind, int $id, CodLedger $ledger)
    {
        $ledger->requireStorage();
        [$model, $field] = match ($kind) { 'collection' => [CodCollection::class, 'evidence_path'],
            'remittance' => [CodRemittance::class, 'evidence_path'], 'receipt' => [CodRemittance::class, 'receipt_evidence_path'],
            'discrepancy' => [CodDiscrepancy::class, 'evidence_path'], default => abort(404) };
        $row = $model::findOrFail($id); $owner = $row instanceof CodDiscrepancy ? $row->remittance : $row; $user = $request->user();
        abort_unless(($user->usertype === 'admin' && $user->status === 'approved')
            || ($user->usertype === 'rider' && (int) $user->rider?->id === (int) $owner->rider_id)
            || ($user->usertype === 'sorting_center' && $user->status === 'approved' && $user->sortingCenter?->approval_status === 'approved'
                && (int) $user->sortingCenter?->id === (int) $owner->sorting_center_id), 404);
        abort_unless($row->$field && Storage::disk('local')->exists($row->$field), 404);
        return Storage::disk('local')->response($row->$field, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
