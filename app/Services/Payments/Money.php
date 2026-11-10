<?php

namespace App\Services\Payments;

use Illuminate\Validation\ValidationException;

final class Money
{
    // Keeps every multiply below PHP's signed 64-bit limit.
    public const MAX = 100_000_000_000;

    public static function cents(mixed $amount): int
    {
        if (! is_string($amount) && ! is_int($amount)) {
            throw ValidationException::withMessages(['amount' => 'Pass money as a decimal string, never a floating-point value.']);
        }
        $value = (string) $amount;
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Use a nonnegative decimal amount with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        self::check($cents);
        return $cents;
    }

    public static function check(int $amount): int
    {
        if ($amount < 0 || $amount > self::MAX) {
            throw ValidationException::withMessages(['amount' => 'Amount is outside the supported range.']);
        }
        return $amount;
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percent(int $cents, int $percent): int
    {
        self::check($cents);
        return intdiv($cents * $percent + 50, 100);
    }

    /** floor(amount * part / total), without an overflowing intermediate product. */
    public static function proportion(int $amount, int $part, int $total): int
    {
        self::check($amount); self::check($part); self::check($total);
        if ($total === 0 || $part > $total) throw new \InvalidArgumentException('Invalid proportion.');
        $quotient = $remainder = 0;
        foreach (str_split(decbin($amount)) as $bit) {
            $remainder = $remainder * 2 + ($bit === '1' ? $part : 0);
            $quotient = $quotient * 2 + intdiv($remainder, $total);
            $remainder %= $total;
        }
        return $quotient;
    }

    /** Largest remainder, stable input order resolves ties; sums exactly to the input. */
    public static function split(int $cents, array $weights): array
    {
        self::check($cents);
        $total = array_sum($weights);
        if ($total <= 0 || $total > 1_000_000 || min($weights) < 0) {
            throw new \InvalidArgumentException('Invalid allocation weights.');
        }
        $parts = $remainders = [];
        foreach ($weights as $key => $weight) {
            $parts[$key] = intdiv($cents * $weight, $total);
            $remainders[$key] = ($cents * $weight) % $total;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $cents - array_sum($parts);
        foreach (array_keys($remainders) as $key) {
            if ($left-- <= 0) break;
            $parts[$key]++;
        }
        return $parts;
    }
}
