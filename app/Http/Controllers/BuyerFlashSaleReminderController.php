<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Services\FlashSaleReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyerFlashSaleReminderController extends Controller
{
    /**
     * Toggle reminder for an upcoming or active flash sale.
     */
    public function toggle(Request $request, FlashSale $flashSale): JsonResponse|RedirectResponse
    {
        $result = FlashSaleReminderService::toggleReminder($request->user(), $flashSale);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'reminded' => $result['reminded'],
                'message' => $result['message'],
            ]);
        }

        return back()->with('status', $result['message']);
    }
}

