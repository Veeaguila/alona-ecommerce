<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Electronics', 'Fashion', 'Home & Living', 'Beauty', 'Sports', 'Accessories'] as $name) {
            Category::firstOrCreate(['slug' => str($name)->slug()], ['name' => $name]);
        }
    }
}
