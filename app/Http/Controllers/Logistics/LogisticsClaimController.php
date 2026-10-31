<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\ParcelIncident;
use App\Services\LogisticsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Schema, Storage};

class LogisticsClaimController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        app(LogisticsAudit::class)->requireStorage();
        abort_unless(Schema::hasTable('parcel_incidents'), 503, 'Logistics claim review is temporarily unavailable.');
        $page = ParcelIncident::where('type', 'lost')->whereHas('parcel.logisticsEvents', fn ($query) => $query->where('action', 'logistics_claim_opened'))
            ->with(['parcel:id,order_id,sorting_center_id,seller_id,buyer_id,status', 'parcel.seller:id,name', 'parcel.buyer:id,name', 'reporter:id,name'])
            ->latest('id')->paginate(25);
        $page->getCollection()->each(function ($incident) {
            $incident->setAttribute('claim_reference', 'INC-'.$incident->id);
            $incident->setAttribute('claim_state', 'pending_review');
            $incident->setAttribute('has_evidence', (bool) $incident->evidence_path);
        });
        return response()->json(['data' => $page]);
    }
    public function evidence(Request $request, int $id)
    {
        $incident = ParcelIncident::where('type', 'lost')->findOrFail($id);
        abort_unless($incident->evidence_path && Storage::disk('local')->exists($incident->evidence_path), 404);
        return Storage::disk('local')->response($incident->evidence_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
