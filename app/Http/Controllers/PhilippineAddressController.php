<?php

namespace App\Http\Controllers;

use App\Services\PhilippineAddressService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhilippineAddressController extends Controller
{
    public function index(Request $request, PhilippineAddressService $addresses, string $level)
    {
        abort_unless(in_array($level, ['groups', 'regions', 'provinces', 'cities', 'barangays'], true), 404);
        $input = $request->validate(['parent' => ['nullable', 'regex:/^\d{10}$/'], 'province' => ['nullable', 'regex:/^\d{10}$/'], 'group' => ['nullable', Rule::in(array_keys($addresses::GROUPS))]]);

        return response()->json(['data' => $addresses->list($level, $input['parent'] ?? null, $request->has('province') ? ($input['province'] ?? '') : null, $input['group'] ?? null), 'version' => $addresses::VERSION])
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
