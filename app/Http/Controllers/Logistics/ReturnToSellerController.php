<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\ParcelReturn;
use App\Services\{DeliveryAttempts, ReturnToSeller};
use Illuminate\Http\Request;

class ReturnToSellerController extends Controller
{
    public function index(Request $request, DeliveryAttempts $attempts)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]); $attempts->requireStorage();
        return response()->json(['data' => ParcelReturn::where('sorting_center_id', $request->user()->sortingCenter->id)
            ->with(['parcel:id,order_id,seller_id,buyer_id,assigned_rider_id,status', 'parcel.seller:id,name', 'parcel.buyer:id,name',
                'parcel.deliveryAttempts', 'parcel.assignedRider:id,name', 'rider:id,name'])->latest('id')->paginate(25)]);
    }
    public function initiate(Request $request, int $id, ReturnToSeller $service)
    {
        $input = $request->validate(['rider_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        return response()->json(['message' => 'Return initiated with the current verified custodian.', 'data' => $service->initiate($request->user(), $id, $input['rider_id'], $input['reason'])]);
    }
    public function mine(Request $request, DeliveryAttempts $attempts)
    {
        $attempts->requireStorage(); $input = $request->validate(['view' => ['sometimes', 'in:active,history'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $history = ($input['view'] ?? 'active') === 'history';
        return response()->json(['data' => ParcelReturn::where('rider_id', $request->user()->rider->id)
            ->whereIn('status', $history ? ['handed_over', 'received'] : ['initiated', 'in_transit'])
            ->with(['parcel:id,order_id,seller_id,collection_address,status', 'parcel.seller:id,name'])->latest('id')->paginate(25)]);
    }
    public function pickup(Request $request, int $id, ReturnToSeller $service)
    {
        $request->validate(['physical_confirmation' => ['required', 'accepted']]);
        return response()->json(['message' => 'Return pickup confirmed. Bring the parcel to the Seller.', 'data' => $service->riderAction($request->user(), $id, false)]);
    }
    public function handover(Request $request, int $id, ReturnToSeller $service)
    {
        $request->validate(['physical_confirmation' => ['required', 'accepted']]);
        return response()->json(['message' => 'Returned to Seller; awaiting Seller receipt confirmation. Inventory is unchanged.', 'data' => $service->riderAction($request->user(), $id, true)]);
    }
    public function receive(Request $request, int $id, ReturnToSeller $service)
    {
        $request->validate(['physical_confirmation' => ['required', 'accepted']]);
        $return = $service->receive($request->user(), $id);
        if ($request->expectsJson()) return response()->json(['message' => 'Physical receipt and one-time restocking recorded.', 'data' => $return]);
        return back()->with('success', 'Physical receipt and one-time restocking recorded.');
    }
}
