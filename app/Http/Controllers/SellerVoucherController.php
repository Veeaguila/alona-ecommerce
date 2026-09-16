<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Seller/Vouchers', [
            'vouchers' => $request->user()->vouchers()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);

        $request->user()->vouchers()->create($data);

        return back()->with('status', 'Voucher created.');
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);

        $data = $this->validated($request, $voucher->id);
        $data['code'] = strtoupper($data['code']);

        $voucher->update($data);

        return back()->with('status', 'Voucher updated.');
    }

    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);
        $voucher->delete();

        return back()->with('status', 'Voucher removed.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                'unique:vouchers,code,'.($ignoreId ?? 'NULL').',id,seller_id,'.$request->user()->id,
            ],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                $request->input('type') === 'percentage'
                    ? 'max:100'
                    : 'max:99999999.99',
            ],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);
    }
}
