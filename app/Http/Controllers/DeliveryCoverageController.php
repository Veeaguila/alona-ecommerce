<?php

namespace App\Http\Controllers;

use App\Services\DeliveryCoverageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryCoverageController extends Controller
{
    public function index(DeliveryCoverageService $coverage)
    {
        return response()->json(['data' => $coverage->directory()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, DeliveryCoverageService $coverage)
    {
        $input = $request->validate(['coverage_city_codes' => ['required', 'array', 'min:1', 'max:100'],
            'coverage_city_codes.*' => ['required', 'distinct', 'regex:/^\d{10}$/']]);
        $coverage->cities($input['coverage_city_codes']);
        $areas = DB::transaction(fn () => $coverage->addCities($request->user()->sortingCenter, $input['coverage_city_codes']));

        return response()->json(['message' => 'Service areas saved.', 'data' => $areas], 201);
    }
}
