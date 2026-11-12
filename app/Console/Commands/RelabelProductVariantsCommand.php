<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Database\Seeders\SellerProductCatalogSeeder;
use Illuminate\Console\Command;

class RelabelProductVariantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:relabel-variants {--force : Relabel even if variants already have attributes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Relabel all seeded product variants with realistic category-tailored attributes, colors, and sizes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting product variant relabeling process...');

        $seeder = new SellerProductCatalogSeeder();
        $seeder->setCommand($this);

        $query = Product::with(['variants', 'category']);
        $totalProducts = $query->count();

        if ($totalProducts === 0) {
            $this->warn('No products found in the database.');
            return Command::SUCCESS;
        }

        $this->info("Scanning {$totalProducts} products...");

        $updatedProducts = 0;
        $updatedVariants = 0;
        $force = (bool) $this->option('force');

        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        foreach ($query->cursor() as $product) {
            $variants = $product->variants()->orderBy('id')->get();
            if ($variants->isEmpty()) {
                $bar->advance();
                continue;
            }

            // Check if relabeling is needed
            $needsRelabel = $force || $variants->contains(function ($v) {
                return empty($v->variant_attributes)
                    || in_array($v->color, ['Standard Edition', 'Premium Edition', 'Option', 'Default', null], true)
                    || in_array($v->size, ['Default', 'Plus', 'Standard', null], true);
            });

            if (! $needsRelabel) {
                $bar->advance();
                continue;
            }

            $item = [
                'name' => $product->name,
                'cat_slug' => $product->category?->slug ?? '',
                'image' => $product->image_path,
                'price' => (float) $product->price,
            ];

            $variantPlan = $seeder->getVariantPlanForProduct($product, $item);

            // Update product variation types
            $product->update([
                'variation_types' => $variantPlan['variation_types'],
            ]);

            $prefix = strtoupper(substr($product->name, 0, 3));

            foreach ($variants as $vIdx => $variant) {
                $plan = $variantPlan['variants'][$vIdx % count($variantPlan['variants'])];
                $newSku = $prefix . '-' . $product->id . '-' . $plan['suffix'];

                $variant->update([
                    'color' => $plan['color'],
                    'size' => $plan['size'],
                    'variant_attributes' => $plan['attributes'],
                    'combination_key' => $this->buildCombinationKey($plan['attributes']),
                    'sku' => $variant->sku ?: $newSku,
                ]);

                // Update review records referencing this variant
                Review::where('variant_id', $variant->id)->update([
                    'variant_name' => $variant->label(),
                ]);

                $updatedVariants++;
            }

            $updatedProducts++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Total Products Scanned', 'Products Relabeled', 'Variants Relabeled'],
            [[$totalProducts, $updatedProducts, $updatedVariants]]
        );

        // Display sample relabeled variants
        $sample = ProductVariant::with('product.category')->latest('updated_at')->take(5)->get();
        if ($sample->isNotEmpty()) {
            $this->info('Sample of Relabeled Variants:');
            $sampleRows = $sample->map(fn ($v) => [
                'Product' => substr($v->product->name, 0, 35) . '...',
                'Category' => $v->product->category?->name ?? 'N/A',
                'Color / Spec' => $v->color,
                'Size / Storage' => $v->size,
                'Label' => $v->label(),
                'SKU' => $v->sku,
            ])->all();

            $this->table(['Product', 'Category', 'Color / Spec', 'Size / Storage', 'Label', 'SKU'], $sampleRows);
        }

        $this->info('Variant relabeling completed successfully!');
        return Command::SUCCESS;
    }

    protected function buildCombinationKey(array $attributes): string
    {
        $pairs = [];
        foreach ($attributes as $k => $v) {
            if (filled($v)) {
                $pairs[mb_strtolower(trim((string) $k))] = mb_strtolower(trim((string) $v));
            }
        }
        ksort($pairs);
        $parts = [];
        foreach ($pairs as $k => $v) {
            $parts[] = "{$k}:{$v}";
        }
        return implode('|', $parts);
    }
}
