<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParcelMonitoringController extends Controller
{
    private const FILTERABLE_STATUSES = [
        'IN_HUB_TRANSIT',
        'AT_SORTING_CENTER',
        'OUT_FOR_DELIVERY',
        'DELIVERED',
        'FAILED_RETURNED',
        'LOST',
        'DAMAGED',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(self::FILTERABLE_STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $sortingCenterId = $request->user()->sortingCenter->id;
        $query = Parcel::query()
            ->with([
                'sortedArea:id,name',
                'assignedRider:id,name',
                'orderItem.order:id,shipping_method',
            ])
            ->inSortingCenterWorkflow($sortingCenterId)
            ->orderByDesc('updated_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            // Group every alternative inside the existing company/status scope.
            // Validate IDs before querying a numeric column to avoid MySQL coercion.
            $orderId = preg_match('/\A[1-9][0-9]*\z/', $search)
                ? filter_var($search, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            $query->where(function (Builder $matches) use ($search, $orderId) {
                $matches->where('order_id', $search)
                    ->orWhereHas('orderItem.order', function (Builder $orders) use ($search, $orderId) {
                        $orders->where('order_number', $search);
                        if ($orderId !== false) {
                            $orders->orWhere('id', $orderId);
                        }
                    })
                    ->orWhereHas('buyer', fn (Builder $buyers) => $buyers->whereRaw('LOWER(name) = ?', [mb_strtolower($search)]));
            });
        }

        $parcels = $query->paginate(10)->withQueryString();

        return response()->json([
            'message' => 'Parcel monitoring data retrieved successfully.',
            'data' => $parcels,
        ]);
    }
}
