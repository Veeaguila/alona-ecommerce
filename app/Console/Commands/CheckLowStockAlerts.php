<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SellerNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckLowStockAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stock:check-low-stock {--seller= : Specific seller ID to check}';

    /**
     * The console command description.
     */
    protected $description = 'Scan products and automatically send low-stock notifications to sellers.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sellerId = $this->option('seller');

        $query = Product::query()
            ->where('status', '!=', 'archived')
            ->whereNotNull('seller_id')
            ->where(function ($q) {
                $q->whereColumn('stock', '<=', DB::raw('COALESCE(low_stock_threshold, 5)'));
            });

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $lowStockProducts = $query->get();
        $sentCount = 0;

        foreach ($lowStockProducts as $product) {
            $alert = SellerNotificationService::sendLowStockAlert($product);
            if ($alert) {
                $sentCount++;
                $this->info("Alert sent for product: {$product->name} (Stock: {$product->stock}, Seller ID: {$product->seller_id})");
            }
        }

        $this->info("Low-stock check completed. {$sentCount} new notification(s) dispatched across {$lowStockProducts->count()} low-stock item(s).");

        return self::SUCCESS;
    }
}

