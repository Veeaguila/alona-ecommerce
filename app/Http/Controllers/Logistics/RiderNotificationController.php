<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\RiderNotification;
use Illuminate\Http\Request;

class RiderNotificationController extends Controller
{
    private function own(Request $request)
    {
        abort_unless($request->user()->usertype === 'rider' && $request->user()->rider, 403);

        return RiderNotification::where('user_id', $request->user()->id);
    }

    public function index(Request $request)
    {
        $input = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $own = $this->own($request);
        $rows = (clone $own)->orderByDesc('created_at')->orderByDesc('id')->paginate($input['per_page'] ?? 30);

        return response()->json(['data' => $rows->items(), 'unread_count' => (clone $own)->whereNull('read_at')->count(),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $id)
    {
        $row = $this->own($request)->whereKey($id)->firstOrFail();
        $this->own($request)->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => $row->fresh()])->header('Cache-Control', 'private, no-store');
    }
}
