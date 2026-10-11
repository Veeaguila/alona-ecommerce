<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderManagementController extends Controller
{
    /**
     * List riders filtered optionally by approval_status.
     * GET /api/sorting-center/riders?status=pending
     * GET /api/sorting-center/riders?status=approved
     */
    public function index(Request $request): JsonResponse
    {
        $query = Rider::query()
            ->with('user:id,name,email')
            ->where('sorting_center_id', $request->user()->sortingCenter->id);

        if ($request->filled('status')) {
            $query->where('approval_status', $request->query('status'));
        }

        $riders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Riders retrieved successfully.',
            'data' => $riders,
        ], 200);
    }

    /**
     * Approve a pending rider application.
     * POST /api/sorting-center/riders/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $rider = DB::transaction(function () use ($id, $sortingCenterId): ?Rider {
            $rider = Rider::query()
                ->where('sorting_center_id', $sortingCenterId)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$rider || $rider->approval_status !== 'pending') {
                return null;
            }

            $rider->update([
                'approval_status' => 'approved',
                'is_active' => true,
            ]);

            $rider->user->update(['status' => $rider->approval_status]);
            return $rider->load('user:id,name,email');
        });

        if (!$rider) {
            return response()->json([
                'message' => 'Pending rider application not found for this sorting center.',
            ], 404);
        }

        return response()->json([
            'message' => 'Rider approved successfully.',
            'data' => $rider,
        ], 200);
    }

    /**
     * Reject a pending rider application.
     * POST /api/sorting-center/riders/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $rider = DB::transaction(function () use ($id, $sortingCenterId): ?Rider {
            $rider = Rider::query()
                ->where('sorting_center_id', $sortingCenterId)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$rider || $rider->approval_status !== 'pending') {
                return null;
            }

            $rider->update(['approval_status' => 'rejected']);
            $rider->user->update(['status' => 'rejected']);

            return $rider->load('user:id,name,email');
        });

        if (!$rider) {
            return response()->json([
                'message' => 'Pending rider application not found for this sorting center.',
            ], 404);
        }

        return response()->json([
            'message' => 'Rider rejected successfully.',
            'data' => $rider,
        ], 200);
    }

    /**
     * Activate an approved rider.
     * POST /api/sorting-center/riders/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $rider = Rider::query()
            ->where('sorting_center_id', $request->user()->sortingCenter->id)
            ->with('user:id,name,email')
            ->find($id);

        if (!$rider) {
            return response()->json([
                'message' => 'Rider not found.',
            ], 404);
        }

        if ($rider->approval_status !== 'approved') {
            return response()->json([
                'message' => 'Only approved riders can be activated.',
                'approval_status' => $rider->approval_status,
            ], 422);
        }

        $rider->is_active = true;
        $rider->save();

        return response()->json([
            'message' => 'Rider activated successfully.',
            'data' => $rider,
        ], 200);
    }

    /**
     * Deactivate an approved rider.
     * POST /api/sorting-center/riders/{id}/deactivate
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $rider = Rider::query()
            ->where('sorting_center_id', $request->user()->sortingCenter->id)
            ->with('user:id,name,email')
            ->find($id);

        if (!$rider) {
            return response()->json([
                'message' => 'Rider not found.',
            ], 404);
        }

        $rider->is_active = false;
        $rider->save();

        return response()->json([
            'message' => 'Rider deactivated successfully.',
            'data' => $rider,
        ], 200);
    }
}
