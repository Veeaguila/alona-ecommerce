<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class BuyerCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount([
                'products' => fn ($query) =>
                    $query->where('status', 'approved'),
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
            ]);

        return Inertia::render('Buyer/Categories', [
            'categories' => $categories,
        ]);
    }
}