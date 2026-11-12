<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\LogisticsSmartScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartScanController extends Controller
{
    public function scan(Request $request, LogisticsSmartScan $scanner): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
            'hub_id' => ['nullable', 'integer'],
            'transport_reference' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $options = [
            'hub_id' => $validated['hub_id'] ?? null,
            'transport_reference' => $validated['transport_reference'] ?? null,
            'photo' => $request->file('photo'),
            'notes' => $validated['notes'] ?? null,
        ];

        $result = $scanner->scan($request->user(), $validated['reference'], $options);

        return response()->json($result);
    }
}
