<?php

namespace App\Services;

use App\Models\BuyerNotification;
use App\Models\FlashSale;
use App\Models\FlashSaleReminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FlashSaleReminderService
{
    /**
     * Toggle a flash sale reminder for a user.
     */
    public static function toggleReminder(User $user, FlashSale $flashSale): array
    {
        if (!Schema::hasTable('flash_sale_reminders')) {
            return [
                'reminded' => false,
                'message' => 'Flash sale reminder system is not available.',
            ];
        }

        $existing = FlashSaleReminder::query()
            ->where('user_id', $user->id)
            ->where('flash_sale_id', $flashSale->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return [
                'reminded' => false,
                'message' => 'Reminder removed.',
            ];
        }

        FlashSaleReminder::create([
            'user_id' => $user->id,
            'flash_sale_id' => $flashSale->id,
            'product_id' => $flashSale->product_id,
        ]);

        return [
            'reminded' => true,
            'message' => 'Reminder set! You will receive a notification before the flash sale starts.',
        ];
    }

    /**
     * Dispatch due flash sale reminders to buyers.
     *
     * @param Carbon|null $currentTime Reference time (defaults to now)
     * @param int $leadMinutes How many minutes before slot starts to trigger (defaults to 15)
     */
    public static function sendDueReminders(?Carbon $currentTime = null, int $leadMinutes = 15): int
    {
        if (!Schema::hasTable('flash_sale_reminders') || !Schema::hasTable('buyer_notifications')) {
            return 0;
        }

        $now = $currentTime ? $currentTime->copy() : Carbon::now();
        $cutoff = $now->copy()->addMinutes($leadMinutes);

        $pendingReminders = FlashSaleReminder::query()
            ->whereNull('notified_at')
            ->whereHas('flashSale', function ($query) use ($now, $cutoff) {
                $query->whereNull('ended_at')
                    ->where('ends_at', '>', $now)
                    ->where('starts_at', '<=', $cutoff);
            })
            ->with(['product:id,name', 'flashSale'])
            ->get();

        $sentCount = 0;

        foreach ($pendingReminders as $reminder) {
            $productName = $reminder->product?->name ?? 'Product';
            $startTime = $reminder->flashSale?->starts_at?->format('H:i') ?? 'soon';
            $flashPrice = number_format((float) ($reminder->flashSale?->flash_price ?? 0), 2);

            BuyerNotification::create([
                'user_id' => $reminder->user_id,
                'type' => 'flash_sale_reminder',
                'title' => "⚡ Flash Sale Reminder: {$productName}",
                'message' => "Flash sale starts at {$startTime} for ₱{$flashPrice}! Don't miss out.",
                'action_url' => $reminder->product_id ? "/buyer/product/{$reminder->product_id}" : null,
            ]);

            $reminder->update([
                'notified_at' => $now,
            ]);

            $sentCount++;
        }

        return $sentCount;
    }
}

