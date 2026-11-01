<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\RiderPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderPresenceController extends Controller
{
    public function show(Request $request, RiderPresence $presence): JsonResponse
    {
        $rider = $request->user()->rider;
        return response()->json(['data' => $presence->snapshot($rider) + ['is_current_session' => $presence->currentSession($rider, $this->session($request))]])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, RiderPresence $presence): JsonResponse
    {
        $input = $request->validate(['action' => ['required', 'string', 'in:go_online,heartbeat,go_offline']]);
        // Re-read eligibility; an authenticated token alone is not operational approval.
        $rider = Rider::where('user_id', $request->user()->id)->operationallyEligible()->first();
        abort_unless($rider, 403, 'This Rider cannot go Online.');
        return response()->json(['data' => $presence->command($rider, $input['action'], $this->session($request))])->header('Cache-Control', 'no-store');
    }

    private function session(Request $request): string
    {
        $token = $request->user()->currentAccessToken();
        return $token && method_exists($token, 'getKey') ? 'token:'.$token->getKey() : 'user:'.$request->user()->id;
    }
}
