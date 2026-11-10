<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\VariantType;
use App\Models\VariantValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminVariantController extends Controller
{
    /**
     * Display master variant catalog, category mappings, and coverage report.
     */
    public function index(Request $request): Response
    {
        $types = VariantType::with(['values' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount(['categories'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = Category::with(['parent:id,name,slug', 'variantTypes:id,name,slug,display_type'])
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->get();

        // Build Category Coverage Report
        $coverage = $categories->map(function ($cat) {
            $effectiveTypes = $cat->getEffectiveVariantTypes();
            $totalOptions = $effectiveTypes->sum(fn ($t) => $t->activeValues->count());
            $isInherited = $cat->variantTypes->isEmpty() && $cat->parent_id && $effectiveTypes->isNotEmpty();

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'parent_name' => $cat->parent?->name,
                'is_subcategory' => (bool) $cat->parent_id,
                'direct_types_count' => $cat->variantTypes->count(),
                'effective_types_count' => $effectiveTypes->count(),
                'total_predefined_options' => $totalOptions,
                'is_inherited' => $isInherited,
                'effective_type_names' => $effectiveTypes->pluck('name')->all(),
                'needs_review' => $effectiveTypes->isEmpty(),
                'status' => $effectiveTypes->isNotEmpty() ? 'Covered' : 'Requires Review',
            ];
        });

        return Inertia::render('Admin/Variants', [
            'title' => 'Master Variant Catalog',
            'eyebrow' => 'Catalog Architecture',
            'description' => 'Manage centralized predefined variant types, approved values, and category mappings.',
            'active' => 'variants',
            'variantTypes' => $types,
            'categories' => $categories,
            'coverage' => $coverage,
            'summary' => [
                'total_types' => $types->count(),
                'total_values' => $types->sum(fn ($t) => $t->values->count()),
                'total_categories' => $categories->count(),
                'covered_categories' => $coverage->where('status', 'Covered')->count(),
                'review_required' => $coverage->where('status', 'Requires Review')->count(),
            ],
        ]);
    }

    /**
     * Store a new variant type.
     */
    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:variant_types,slug'],
            'display_type' => ['required', 'string', Rule::in(['color_swatch', 'pills', 'select', 'button'])],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            if (VariantType::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] .= '-' . Str::random(4);
            }
        }

        $type = VariantType::create($validated);

        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'created_variant_type',
            'target_type' => VariantType::class,
            'target_id' => $type->id,
            'details' => json_encode(['name' => $type->name, 'slug' => $type->slug]),
        ]);

        return back()->with('status', "Variant type '{$type->name}' created successfully.");
    }

    /**
     * Update an existing variant type.
     */
    public function updateType(Request $request, VariantType $variantType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'display_type' => ['required', 'string', Rule::in(['color_swatch', 'pills', 'select', 'button'])],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $variantType->update($validated);

        return back()->with('status', "Variant type '{$variantType->name}' updated successfully.");
    }

    /**
     * Toggle active status of a variant type.
     */
    public function toggleType(VariantType $variantType): RedirectResponse
    {
        $variantType->update(['is_active' => ! $variantType->is_active]);

        return back()->with('status', "Variant type '{$variantType->name}' status updated.");
    }

    /**
     * Store a new predefined variant value.
     */
    public function storeValue(Request $request, VariantType $variantType): RedirectResponse
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:191',
                Rule::unique('variant_values', 'value')->where('variant_type_id', $variantType->id),
            ],
            'label' => ['required', 'string', 'max:191'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $value = $variantType->values()->create($validated);

        return back()->with('status', "Value '{$value->label}' added to {$variantType->name}.");
    }

    /**
     * Update a predefined variant value.
     */
    public function updateValue(Request $request, VariantValue $variantValue): RedirectResponse
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:191',
                Rule::unique('variant_values', 'value')
                    ->where('variant_type_id', $variantValue->variant_type_id)
                    ->ignore($variantValue->id),
            ],
            'label' => ['required', 'string', 'max:191'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $variantValue->update($validated);

        return back()->with('status', "Variant value '{$variantValue->label}' updated.");
    }

    /**
     * Toggle active status of a variant value.
     */
    public function toggleValue(VariantValue $variantValue): RedirectResponse
    {
        $variantValue->update(['is_active' => ! $variantValue->is_active]);

        return back()->with('status', "Variant value '{$variantValue->label}' status updated.");
    }

    /**
     * Update category variant mappings.
     */
    public function updateCategoryMappings(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'variant_type_ids' => ['array'],
            'variant_type_ids.*' => ['integer', 'exists:variant_types,id'],
        ]);

        $category->variantTypes()->sync($validated['variant_type_ids'] ?? []);

        return back()->with('status', "Variant mappings updated for category '{$category->name}'.");
    }

    /**
     * API: Get coverage report as JSON.
     */
    public function coverageReport(): JsonResponse
    {
        $categories = Category::with(['parent:id,name,slug', 'variantTypes:id,name,slug,display_type'])
            ->orderBy('name')
            ->get();

        $report = $categories->map(function ($cat) {
            $effectiveTypes = $cat->getEffectiveVariantTypes();
            return [
                'id' => $cat->id,
                'category' => $cat->name,
                'slug' => $cat->slug,
                'parent' => $cat->parent?->name ?? 'Root',
                'status' => $effectiveTypes->isNotEmpty() ? 'Covered' : 'Requires Review',
                'mapped_types' => $effectiveTypes->pluck('name')->all(),
                'options_count' => $effectiveTypes->sum(fn ($t) => $t->activeValues->count()),
            ];
        });

        $total = $categories->count();
        $covered = $report->where('status', 'Covered')->count();

        return response()->json([
            'total_categories' => $total,
            'covered' => $covered,
            'covered_categories' => $covered,
            'requires_review' => $report->where('status', 'Requires Review')->count(),
            'coverage_percentage' => $total > 0 ? round(($covered / $total) * 100, 1) : 0,
            'categories' => $report,
        ]);
    }
}
