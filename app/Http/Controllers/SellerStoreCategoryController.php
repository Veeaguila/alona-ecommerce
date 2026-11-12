<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StoreCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerStoreCategoryController extends Controller
{
    /**
     * List store categories for the authenticated seller.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $seller = $request->user();

        $categories = StoreCategory::query()
            ->where('seller_id', $seller->id)
            ->whereNull('parent_id')
            ->withCount('products')
            ->with([
                'children' => fn ($query) => $query->withCount('products')->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get();

        $uncategorizedCount = Product::query()
            ->where('seller_id', $seller->id)
            ->whereDoesntHave('storeCategories')
            ->count();

        $products = Product::query()
            ->where('seller_id', $seller->id)
            ->select('id', 'name', 'price', 'stock', 'status', 'category_id', 'image_path')
            ->with('storeCategories:id,name')
            ->latest()
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'categories' => $categories,
                'uncategorized_count' => $uncategorizedCount,
                'products' => $products,
            ]);
        }

        return Inertia::render('Seller/StoreCategories', [
            'categories' => $categories,
            'uncategorized_count' => $uncategorizedCount,
            'products' => $products,
        ]);
    }

    /**
     * Create a new store category.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $seller = $request->user();

        $rawName = $request->input('name');
        $trimmedName = is_string($rawName) ? trim($rawName) : '';

        if ($trimmedName === '') {
            throw ValidationException::withMessages([
                'name' => 'Category name is required',
            ]);
        }

        if (mb_strlen($trimmedName) > 50) {
            throw ValidationException::withMessages([
                'name' => 'Category name cannot exceed 50 characters',
            ]);
        }

        // Duplicate check within the same store (case-insensitive & trimmed) - SEL-SCAT-003
        $isDuplicate = StoreCategory::query()
            ->where('seller_id', $seller->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmedName)])
            ->exists();

        if ($isDuplicate) {
            throw ValidationException::withMessages([
                'name' => 'A category with this name already exists in your store',
            ]);
        }

        // Depth limit: max 2 levels (SEL-SCAT-011)
        $parentId = $request->input('parent_id');
        if ($parentId) {
            $parent = StoreCategory::where('seller_id', $seller->id)->find($parentId);

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent category is invalid.',
                ]);
            }

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Maximum category nesting depth of 2 levels reached',
                ]);
            }
        }

        // Determine sort order
        $sortOrder = $request->input('sort_order');
        if ($sortOrder === null) {
            $maxSort = StoreCategory::where('seller_id', $seller->id)
                ->where('parent_id', $parentId ?: null)
                ->max('sort_order');
            $sortOrder = ($maxSort ?? 0) + 1;
        }

        // Unique slug per seller
        $baseSlug = Str::slug($trimmedName);
        if ($baseSlug === '') {
            $baseSlug = 'category';
        }
        $slug = $baseSlug;
        $counter = 1;
        while (StoreCategory::where('seller_id', $seller->id)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $category = StoreCategory::create([
            'seller_id' => $seller->id,
            'parent_id' => $parentId ?: null,
            'name' => $trimmedName,
            'slug' => $slug,
            'sort_order' => (int) $sortOrder,
            'is_visible' => $request->boolean('is_visible', true),
        ]);

        // Assign initial products if provided
        if ($request->has('product_ids') && is_array($request->product_ids)) {
            $productIds = $request->product_ids;
            foreach ($productIds as $productId) {
                $product = Product::where('seller_id', $seller->id)->find($productId);
                if ($product && $product->storeCategories()->count() >= 3) {
                    throw ValidationException::withMessages([
                        'product_ids' => 'A product can be assigned to a maximum of 3 store categories',
                    ]);
                }
            }
            $category->products()->syncWithoutDetaching($productIds);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Store category created successfully.',
                'category' => $category->load('children'),
            ], 201);
        }

        return back()->with('status', 'Store category created successfully.');
    }

    /**
     * Update an existing store category.
     */
    public function update(Request $request, StoreCategory $storeCategory): RedirectResponse|JsonResponse
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);

        $seller = $request->user();

        if ($request->has('name')) {
            $rawName = $request->input('name');
            $trimmedName = is_string($rawName) ? trim($rawName) : '';

            if ($trimmedName === '') {
                throw ValidationException::withMessages([
                    'name' => 'Category name is required',
                ]);
            }

            if (mb_strlen($trimmedName) > 50) {
                throw ValidationException::withMessages([
                    'name' => 'Category name cannot exceed 50 characters',
                ]);
            }

            // Rename to duplicate name blocked (SEL-SCAT-005)
            $isDuplicate = StoreCategory::query()
                ->where('seller_id', $seller->id)
                ->where('id', '!=', $storeCategory->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmedName)])
                ->exists();

            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'name' => 'A category with this name already exists in your store',
                ]);
            }

            $storeCategory->name = $trimmedName;
            $storeCategory->slug = Str::slug($trimmedName);
        }

        if ($request->has('parent_id')) {
            $parentId = $request->input('parent_id');

            if ($parentId) {
                if ((int) $parentId === (int) $storeCategory->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A category cannot be its own parent.',
                    ]);
                }

                $parent = StoreCategory::where('seller_id', $seller->id)->find($parentId);
                if (! $parent) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'The selected parent category is invalid.',
                    ]);
                }

                // If target parent already has a parent, or if current category has children, depth exceeds 2
                if ($parent->parent_id !== null || $storeCategory->children()->exists()) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Maximum category nesting depth of 2 levels reached',
                    ]);
                }

                $storeCategory->parent_id = $parentId;
            } else {
                $storeCategory->parent_id = null;
            }
        }

        if ($request->has('sort_order')) {
            $storeCategory->sort_order = (int) $request->input('sort_order');
        }

        if ($request->has('is_visible')) {
            $storeCategory->is_visible = $request->boolean('is_visible');
        }

        $storeCategory->save();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Store category updated successfully.',
                'category' => $storeCategory->fresh(['children']),
            ]);
        }

        return back()->with('status', 'Store category updated successfully.');
    }

    /**
     * Reorder multiple store categories.
     */
    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $seller = $request->user();
        $categoriesData = $request->input('categories', []);

        if (is_array($categoriesData)) {
            foreach ($categoriesData as $item) {
                if (isset($item['id'], $item['sort_order'])) {
                    StoreCategory::query()
                        ->where('seller_id', $seller->id)
                        ->where('id', $item['id'])
                        ->update(['sort_order' => (int) $item['sort_order']]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Categories reordered successfully.']);
        }

        return back()->with('status', 'Categories reordered successfully.');
    }

    /**
     * Delete a store category (and its subcategories if any).
     * Attached products are detached and remain active/buyable in Uncategorized.
     */
    public function destroy(Request $request, StoreCategory $storeCategory): RedirectResponse|JsonResponse
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);

        $subcategories = $storeCategory->children;

        // Collect stats for notification or confirmation response
        $affectedSubcategoriesCount = $subcategories->count();
        $affectedProductIds = $storeCategory->products()->pluck('products.id')->toArray();

        foreach ($subcategories as $subcategory) {
            $affectedProductIds = array_merge(
                $affectedProductIds,
                $subcategory->products()->pluck('products.id')->toArray()
            );
            // Delete subcategory (products detach via DB cascade on pivot table, products remain intact)
            $subcategory->delete();
        }

        // Delete parent category (products detach via DB cascade on pivot table, products remain intact)
        $storeCategory->delete();

        $affectedProductCount = count(array_unique($affectedProductIds));

        $message = $affectedSubcategoriesCount > 0
            ? "Category and {$affectedSubcategoriesCount} subcategory(ies) deleted. {$affectedProductCount} product(s) moved to Uncategorized."
            : "Category deleted. {$affectedProductCount} product(s) moved to Uncategorized.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'affected_subcategories' => $affectedSubcategoriesCount,
                'affected_products' => $affectedProductCount,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Assign or sync store categories for a specific product.
     * Enforces maximum of 3 store categories (SEL-SCAT-012).
     * Does NOT alter platform category_id (SEL-SCAT-013).
     */
    public function assignProductCategories(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $seller = $request->user();
        $categoryIds = $request->input('store_category_ids', []);

        if (! is_array($categoryIds)) {
            throw ValidationException::withMessages([
                'store_category_ids' => 'Store category IDs must be an array.',
            ]);
        }

        // Max 3 store categories per product (SEL-SCAT-012)
        if (count($categoryIds) > 3) {
            throw ValidationException::withMessages([
                'store_category_ids' => 'A product can be assigned to a maximum of 3 store categories',
            ]);
        }

        // Validate that all categories belong to this seller
        if (! empty($categoryIds)) {
            $validCount = StoreCategory::query()
                ->where('seller_id', $seller->id)
                ->whereIn('id', $categoryIds)
                ->count();

            if ($validCount !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'store_category_ids' => 'One or more selected categories are invalid.',
                ]);
            }
        }

        // Preserve platform category_id (SEL-SCAT-013)
        $originalCategoryId = $product->category_id;

        $product->storeCategories()->sync($categoryIds);

        // Ensure platform category wasn't touched
        if ($product->category_id !== $originalCategoryId) {
            $product->category_id = $originalCategoryId;
            $product->save();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product store categories updated successfully.',
                'product' => $product->fresh(['storeCategories']),
            ]);
        }

        return back()->with('status', 'Product store categories updated successfully.');
    }

    /**
     * Add multiple products into a specific store category.
     */
    public function addProducts(Request $request, StoreCategory $storeCategory): RedirectResponse|JsonResponse
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);

        $seller = $request->user();
        $productIds = $request->input('product_ids', []);

        if (! is_array($productIds) || empty($productIds)) {
            throw ValidationException::withMessages([
                'product_ids' => 'Please select at least one product.',
            ]);
        }

        foreach ($productIds as $productId) {
            $product = Product::where('seller_id', $seller->id)->find($productId);
            if (! $product) {
                continue;
            }

            // Check if already in this category
            $alreadyAssigned = $product->storeCategories()
                ->where('store_categories.id', $storeCategory->id)
                ->exists();

            if (! $alreadyAssigned) {
                // If adding this would exceed 3 categories (SEL-SCAT-012)
                if ($product->storeCategories()->count() >= 3) {
                    throw ValidationException::withMessages([
                        'product_ids' => 'A product can be assigned to a maximum of 3 store categories',
                    ]);
                }

                $product->storeCategories()->attach($storeCategory->id);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Products added to store category successfully.',
            ]);
        }

        return back()->with('status', 'Products added to store category successfully.');
    }

    /**
     * Remove a product from a specific store category.
     */
    public function removeProduct(Request $request, StoreCategory $storeCategory, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);
        abort_unless($product->seller_id === $request->user()->id, 403);

        $product->storeCategories()->detach($storeCategory->id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product removed from store category successfully.',
            ]);
        }

        return back()->with('status', 'Product removed from store category successfully.');
    }
}

