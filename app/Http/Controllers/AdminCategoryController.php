<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCategoryController extends Controller
{
    /**
     * Display categories directory with product counts, parent categories, and active status.
     */
    public function index(Request $request): Response
    {
        $query = Category::query()
            ->with(['parent:id,name,slug'])
            ->withCount(['products', 'children']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('type') && $request->input('type') !== 'all') {
            if ($request->input('type') === 'root') {
                $query->whereNull('parent_id');
            } elseif ($request->input('type') === 'sub') {
                $query->whereNotNull('parent_id');
            }
        }

        $categories = $query->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->paginate(20)
            ->withQueryString();

        // Get top-level (root) categories for parent dropdown
        $rootCategories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Categories', [
            'title' => 'Categories & Taxonomy',
            'eyebrow' => 'Catalog Architecture',
            'description' => 'Manage product categories and subcategories, organize catalog taxonomy, and toggle visibility.',
            'active' => 'categories',
            'categories' => $categories,
            'rootCategories' => $rootCategories,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', 'all'),
                'type' => (string) $request->input('type', 'all'),
            ],
        ]);
    }

    /**
     * Store a newly created category or subcategory.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $category = Category::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim($validated['name']),
            'slug' => $slug,
            'image_path' => $validated['image_path'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $parentName = $category->parent?->name;
        $description = $parentName
            ? "Created subcategory '{$category->name}' under '{$parentName}' (slug: {$category->slug})"
            : "Created product category '{$category->name}' (slug: {$category->slug})";

        AdminAuditLog::record(
            $request->user(),
            'category_created',
            $description,
            $category,
            ['name' => $category->name, 'slug' => $category->slug, 'parent_id' => $category->parent_id]
        );

        return back()->with('status', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update an existing category or subcategory.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $descendantIds = $category->getDescendantIds();

        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn($descendantIds), // Cannot set itself or its descendants as its parent
            ],
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $category->id],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug,' . $category->id],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldData = $category->only(['parent_id', 'name', 'slug', 'is_active', 'image_path']);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : $category->slug;

        $category->update([
            'parent_id' => $validated['parent_id'] ?? null,
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
            ['old' => $oldData, 'new' => $category->only(['parent_id', 'name', 'slug', 'is_active', 'image_path'])]
        );

        return back()->with('status', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Delete a category.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $childCount = $category->children()->count();
        if ($childCount > 0) {
            return back()->withErrors([
                'error' => "Cannot delete category '{$category->name}' because it contains {$childCount} subcategory(ies). Please reassign or delete the subcategories first.",
            ]);
        }

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
