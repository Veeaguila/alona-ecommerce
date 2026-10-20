<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminCategoryController extends Controller
{
    /**
     * Display categories directory with product counts and active status.
     */
    public function index(Request $request): Response
    {
        $query = Category::query()->withCount('products');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $categories = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Categories', [
            'title' => 'Categories & Taxonomy',
            'eyebrow' => 'Catalog Architecture',
            'description' => 'Manage product categories, organize catalog taxonomy, and toggle visibility.',
            'active' => 'categories',
            'categories' => $categories,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $category = Category::create([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'image_path' => $validated['image_path'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AdminAuditLog::record(
            $request->user(),
            'category_created',
            "Created product category '{$category->name}' (slug: {$category->slug})",
            $category,
            ['name' => $category->name, 'slug' => $category->slug]
        );

        return back()->with('status', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $category->id],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug,' . $category->id],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldData = $category->only(['name', 'slug', 'is_active', 'image_path']);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : $category->slug;

        $category->update([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'image_path' => $validated['image_path'] ?? $category->image_path,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        AdminAuditLog::record(
            $request->user(),
            'category_updated',
            "Updated product category '{$category->name}'",
            $category,
            ['old' => $oldData, 'new' => $category->only(['name', 'slug', 'is_active', 'image_path'])]
        );

        return back()->with('status', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Delete a category.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $productCount = $category->products()->count();
        if ($productCount > 0) {
            return back()->withErrors([
                'error' => "Cannot delete category '{$category->name}' because it contains {$productCount} product(s). Please reassign or delete the products first.",
            ]);
        }

        $categoryName = $category->name;
        $category->delete();

        AdminAuditLog::record(
            $request->user(),
            'category_deleted',
            "Deleted product category '{$categoryName}'",
            null,
            ['name' => $categoryName]
        );

        return back()->with('status', "Category '{$categoryName}' deleted successfully.");
    }
}

