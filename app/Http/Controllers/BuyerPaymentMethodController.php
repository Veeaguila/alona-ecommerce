<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerPaymentMethodController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Buyer/PaymentMethods', [
            'payment_methods' => $request->user()
                ->paymentMethods()
                ->orderByDesc('is_default')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:gcash,maya,card'],
            'holder_name' => ['required', 'string', 'max:100'],
            // Card number or mobile number. Only the last 4 digits are kept.
            'number' => ['required', 'string', 'max:25'],
            'exp_month' => ['nullable', 'required_if:type,card', 'integer', 'between:1,12'],
            'exp_year' => ['nullable', 'required_if:type,card', 'integer', 'min:' . now()->year, 'max:' . (now()->year + 20)],
            'is_default' => ['boolean'],
        ]);

        $digits = preg_replace('/\D+/', '', $data['number']);

        if ($data['type'] === 'card') {
            if (strlen($digits) < 13 || strlen($digits) > 19 || !$this->luhn($digits)) {
                throw ValidationException::withMessages([
                    'number' => 'Please enter a valid card number.',
                ]);
            }

            $expiry = \Carbon\Carbon::create((int) $data['exp_year'], (int) $data['exp_month'], 1)->endOfMonth();

            if ($expiry->isPast()) {
                throw ValidationException::withMessages([
                    'exp_month' => 'This card has expired.',
                ]);
            }
        } elseif (strlen($digits) < 10 || strlen($digits) > 13) {
            throw ValidationException::withMessages([
                'number' => 'Please enter a valid mobile number.',
            ]);
        }

        $user = $request->user();
        $makeDefault = (bool) ($data['is_default'] ?? false)
            || !$user->paymentMethods()->exists();

        if ($makeDefault) {
            $user->paymentMethods()->update(['is_default' => false]);
        }

        $user->paymentMethods()->create([
            'type' => $data['type'],
            'brand' => $data['type'] === 'card' ? $this->brand($digits) : null,
            'holder_name' => $data['holder_name'],
            'last_four' => substr($digits, -4),
            'exp_month' => $data['type'] === 'card' ? $data['exp_month'] : null,
            'exp_year' => $data['type'] === 'card' ? $data['exp_year'] : null,
            'is_default' => $makeDefault,
        ]);

        return back()->with('status', 'Payment method saved.');
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        abort_unless((int) $paymentMethod->user_id === (int) $request->user()->id, 404);

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        if ($wasDefault) {
            $request->user()->paymentMethods()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', 'Payment method removed.');
    }

    private function luhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return $sum % 10 === 0;
    }

    private function brand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4') => 'Visa',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $number) => 'Mastercard',
            (bool) preg_match('/^35/', $number) => 'JCB',
            default => 'Card',
        };
    }
}

