<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Database\Seeders\VariantCatalogSeeder;
use Illuminate\Console\Command;

class SyncCategoryVariantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'variants:sync-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Organize categories into a Department/Subcategory hierarchy and assign approved predefined variant types';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting category hierarchy and variant catalog sync...');

        $this->info('1. Organizing Department & Subcategory taxonomy...');
        $categorySeeder = new CategorySeeder();
        $categorySeeder->setCommand($this);
        $categorySeeder->run();
        $this->info('✓ Categories and subcategories organized.');

        $this->info('2. Syncing master variant types, approved values, and category mappings...');
        $variantSeeder = new VariantCatalogSeeder();
        $variantSeeder->setCommand($this);
        $variantSeeder->run();
        $this->info('✓ Variant types and category mappings synchronized successfully.');

        $this->newLine();
        $this->info('Summary of Category-Variant coverage:');
        
        $categories = \App\Models\Category::with(['parent', 'activeVariantTypes'])->get();
        $covered = 0;
        foreach ($categories as $cat) {
            $effective = $cat->getEffectiveVariantTypes();
            if ($effective->isNotEmpty()) {
                $covered++;
            }
        }

        $this->table(
            ['Total Categories', 'Covered with Variants', 'Coverage Rate'],
            [
                [$categories->count(), $covered, round(($covered / max(1, $categories->count())) * 100) . '%']
            ]
        );

        $this->info('Category and variant synchronization completed.');
        return Command::SUCCESS;
    }
}
