<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\SortingCenter;
use Illuminate\Http\JsonResponse;

class SortingCenterDirectoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SortingCenter::query()
                ->availableForEnrollment()
                ->orderBy('business_name')
                ->get(['id', 'business_name']),
        ]);
    }
}
