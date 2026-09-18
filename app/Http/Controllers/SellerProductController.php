<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SellerProductController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PRODUCT LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): Response
    {
        $seller = $request->user();

        $products = Product::with('category:id,name')
            ->withCount([
                'orderItems as sold_count' => fn ($q) =>
                    $q->whereIn('status', ['delivered', 'completed']),
            ])
            ->where('seller_id', $seller->id)

            ->when(
                $request->filled('search'),
                fn ($q) =>
                    $q->where(
                        'name',
                        'like',
                        '%' . $request->input('search') . '%'
                    )
            )

            ->when(
                $request->filled('category') &&
                $request->input('category') !== 'All Categories',
                function ($q) use ($request) {
                    $q->whereHas(
                        'category',
                        fn ($cq) =>
                            $cq->where(
                                'name',
                                $request->input('category')
                            )
                    );
                }
            )

            ->when(
                $request->filled('status') &&
                $request->input('status') !== 'All Status',
                function ($q) use ($request) {
                    match ($request->input('status')) {
                        'Archived' =>
                            $q->where('status', 'archived'),

                        'Out of Stock' =>
                            $q->where('status', '!=', 'archived')
                                ->where('stock', 0),

                        'Low Stock' =>
                            $q->where('status', '!=', 'archived')
                                ->where('stock', '>', 0)
                                ->where('stock', '<=', 5),

                        'Active' =>
                            $q->where('status', '!=', 'archived')
                                ->where('stock', '>', 5),

                        default => $q,
                    };
                }
            )

            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Seller/Products', [
            'products' => $products,

            'summary' => [
                'total' => Product::where('seller_id', $seller->id)
                    ->where('status', '!=', 'archived')
                    ->count(),

                'active' => Product::where('seller_id', $seller->id)
                    ->where('status', '!=', 'archived')
                    ->where('stock', '>', 5)
                    ->count(),

                'low_stock' => Product::where('seller_id', $seller->id)
                    ->where('status', '!=', 'archived')
                    ->whereBetween('stock', [1, 5])
                    ->count(),

                'out_of_stock' => Product::where('seller_id', $seller->id)
                    ->where('status', '!=', 'archived')
                    ->where('stock', 0)
                    ->count(),

                'archived' => Product::where('seller_id', $seller->id)
                    ->where('status', 'archived')
                    ->count(),
            ],

            /*
             * IMPORTANT:
             * Seller category filters also come from the same
             * categories table used by Buyer Categories.
             */
            'categories' => $this->activeCategories(),

            'filters' => $request->only([
                'search',
                'category',
                'status',
            ]),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | STOCK MONITORING
    |--------------------------------------------------------------------------
    */

    public function stockMonitoring(Request $request): Response
    {
        $seller = $request->user();

        $search = trim((string) $request->input('search', ''));

        $stockStatus = $request->input(
            'stock_status',
            'All Stock'
        );

        $products = Product::query()
            ->where('seller_id', $seller->id)
            ->where('status', '!=', 'archived')

            ->with([
                'category:id,name',
                'variants:id,product_id,color,size,stock',
                'images:id,product_id,image_path,sort_order',
            ])

            ->withCount([
                'orderItems as sold_count' => fn ($q) =>
                    $q->whereIn(
                        'status',
                        ['delivered', 'completed']
                    ),
            ])

            ->when(
                $search !== '',
                fn ($q) =>
                    $q->where(
                        'name',
                        'like',
                        '%' . $search . '%'
                    )
            )

            ->when(
                $stockStatus !== 'All Stock',
                function ($q) use ($stockStatus) {
                    match ($stockStatus) {
                        'Out of Stock' =>
                            $q->where('stock', 0),

                        'Low Stock' =>
                            $q->whereBetween('stock', [1, 5]),

                        'In Stock' =>
                            $q->where('stock', '>', 5),

                        default => $q,
                    };
                }
            )

            ->latest()
            ->get();

        $summary = [
            'total_products' => Product::where(
                'seller_id',
                $seller->id
            )
                ->where('status', '!=', 'archived')
                ->count(),

            'total_stock' => Product::where(
                'seller_id',
                $seller->id
            )
                ->where('status', '!=', 'archived')
                ->sum('stock'),

            'in_stock' => Product::where(
                'seller_id',
                $seller->id
            )
                ->where('status', '!=', 'archived')
                ->where('stock', '>', 5)
                ->count(),

            'low_stock' => Product::where(
                'seller_id',
                $seller->id
            )
                ->where('status', '!=', 'archived')
                ->whereBetween('stock', [1, 5])
                ->count(),

            'out_of_stock' => Product::where(
                'seller_id',
                $seller->id
            )
                ->where('status', '!=', 'archived')
                ->where('stock', 0)
                ->count(),
        ];

        return Inertia::render(
            'Seller/StockMonitoring',
            [
                'products' => $products,
                'summary' => $summary,

                'filters' => [
                    'search' => $search,
                    'stock_status' => $stockStatus,
                ],
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function create(): Response
    {
        return Inertia::render('Seller/AddProduct', [
            /*
             * This is the SAME categories table used by Buyer.
             */
            'categories' => $this->activeCategories(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | STORE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $variants = $this->validateVariants($request);

        if (count($variants) > 0) {
            $validated['stock'] = collect($variants)->sum('stock');
        }

        $validated['seller_id'] = $request->user()->id;

        $validated['slug'] = $this->uniqueSlug(
            $validated['name']
        );

        $validated['status'] = $request->input(
            'status',
            'pending'
        );

        $imagePaths = $this->storeProductImages($request);

        if (count($imagePaths) > 0) {
            $validated['image_path'] = $imagePaths[0];
        }

        unset(
            $validated['image'],
            $validated['images']
        );

        try {
            DB::transaction(function () use (
                $validated,
                $variants,
                $imagePaths
            ) {
                $product = Product::create($validated);

                foreach ($imagePaths as $index => $imagePath) {
                    $product->images()->create([
                        'image_path' => $imagePath,
                        'sort_order' => $index,
                    ]);
                }

                foreach ($variants as $variantData) {
                    $product->variants()->create([
                        'color' => $variantData['color'],
                        'size' => $variantData['size'],
                        'stock' => $variantData['stock'],
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($imagePaths as $imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        if ($validated['status'] === 'draft') {
            return redirect()
                ->route('seller.products')
                ->with(
                    'status',
                    'Product saved as draft.'
                );
        }

        if ($validated['status'] === 'inactive') {
            return redirect()
                ->route('seller.products')
                ->with(
                    'status',
                    'Product saved as inactive.'
                );
        }

        return redirect()
            ->route('seller.products')
            ->with(
                'status',
                'Product submitted for admin approval.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT PRODUCT
    |--------------------------------------------------------------------------
    */

    public function edit(
        Request $request,
        Product $product
    ): Response {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        return Inertia::render('Seller/EditProduct', [
            'product' => $product->load([
                'category:id,name,slug',
                'variants',
                'images',
            ]),

            /*
             * Same source of truth as Add Product
             * and Buyer Categories.
             */
            'categories' => $this->activeCategories(),

            'unansweredQuestions' => $product->questions()
                ->whereNull('answer')
                ->with('buyer:id,name')
                ->latest()
                ->get(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * Only ACTIVE categories can be assigned.
             *
             * This prevents a seller from assigning a product
             * to a category that Buyer Categories cannot display.
             */
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(
                        fn ($query) =>
                            $query->where('is_active', true)
                    ),
            ],

            'price' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'old_price' => [
                'nullable',
                'numeric',
                'min:0.01',
                'gt:price',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'nullable',
                'in:pending,draft,inactive',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
        ]);

        $newImagePaths = $this->storeProductImages($request);

        if (count($newImagePaths) > 0) {
            $oldImagePaths = $product->images()
                ->orderBy('sort_order')
                ->pluck('image_path')
                ->filter()
                ->values()
                ->all();

            if (
                $product->image_path &&
                !in_array(
                    $product->image_path,
                    $oldImagePaths,
                    true
                )
            ) {
                $oldImagePaths[] = $product->image_path;
            }

            $validated['image_path'] = $newImagePaths[0];

            unset(
                $validated['image'],
                $validated['images']
            );

            try {
                DB::transaction(function () use (
                    $product,
                    $validated,
                    $newImagePaths,
                    $oldImagePaths
                ) {
                    $product->update($validated);

                    $product->images()->delete();

                    foreach (
                        $newImagePaths
                        as $index => $imagePath
                    ) {
                        $product->images()->create([
                            'image_path' => $imagePath,
                            'sort_order' => $index,
                        ]);
                    }

                    foreach ($oldImagePaths as $oldImagePath) {
                        if (
                            $oldImagePath !== $newImagePaths[0] &&
                            !in_array(
                                $oldImagePath,
                                $newImagePaths,
                                true
                            )
                        ) {
                            Storage::disk('public')
                                ->delete($oldImagePath);
                        }
                    }
                });
            } catch (\Throwable $exception) {
                foreach ($newImagePaths as $imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }

                throw $exception;
            }
        } else {
            unset(
                $validated['image'],
                $validated['images']
            );

            $product->update($validated);
        }

        /*
         * Any seller edit goes back to pending approval.
         */
        $product->update([
            'status' => 'pending',
        ]);

        return back()->with(
            'status',
            'Product updated and sent back for admin review.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INVENTORY MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function inventory(
        Request $request,
        Product $product
    ): Response {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        $product->load([
            'category:id,name',
            'variants:id,product_id,color,size,stock',
            'images:id,product_id,image_path,sort_order',
        ]);

        return Inertia::render('Seller/Inventory', [
            'product' => $product,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE INVENTORY
    |--------------------------------------------------------------------------
    */

    public function updateInventory(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'variants' => [
                'nullable',
                'array',
            ],

            'variants.*.id' => [
                'required',
                'integer',
                'exists:product_variants,id',
            ],

            'variants.*.stock' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $product
        ) {
            foreach (
                $validated['variants'] ?? []
                as $variantData
            ) {
                $variant = $product->variants()
                    ->whereKey($variantData['id'])
                    ->first();

                abort_unless(
                    $variant,
                    403
                );

                $variant->update([
                    'stock' => $variantData['stock'],
                ]);
            }

            if ($product->variants()->exists()) {
                $totalStock = $product
                    ->variants()
                    ->sum('stock');

                $product->update([
                    'stock' => $totalStock,
                ]);
            } else {
                $product->update([
                    'stock' => $validated['stock'],
                ]);
            }
        });

        return back()->with(
            'status',
            'Inventory updated successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT VARIANT MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function storeVariant(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'color' => [
                'nullable',
                'string',
                'max:100',
            ],

            'size' => [
                'nullable',
                'string',
                'max:100',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $color = filled($validated['color'] ?? null)
            ? trim($validated['color'])
            : null;

        $size = filled($validated['size'] ?? null)
            ? trim($validated['size'])
            : null;

        if (!$color && !$size) {
            return back()->withErrors([
                'color' =>
                    'Please provide a color or size for the variant.',
            ]);
        }

        $exists = $product->variants()
            ->where(function ($query) use ($color) {
                if ($color === null) {
                    $query->whereNull('color');
                } else {
                    $query->where('color', $color);
                }
            })
            ->where(function ($query) use ($size) {
                if ($size === null) {
                    $query->whereNull('size');
                } else {
                    $query->where('size', $size);
                }
            })
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'color' =>
                    'This product variant already exists.',
            ]);
        }

        $product->variants()->create([
            'color' => $color,
            'size' => $size,
            'stock' => $validated['stock'],
        ]);

        $product->update([
            'stock' => $product
                ->variants()
                ->sum('stock'),
        ]);

        return back()->with(
            'status',
            'Product variant added successfully.'
        );
    }


    public function updateVariant(
        Request $request,
        Product $product,
        ProductVariant $variant
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        abort_unless(
            $variant->product_id === $product->id,
            403
        );

        $validated = $request->validate([
            'color' => [
                'nullable',
                'string',
                'max:100',
            ],

            'size' => [
                'nullable',
                'string',
                'max:100',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $color = filled($validated['color'] ?? null)
            ? trim($validated['color'])
            : null;

        $size = filled($validated['size'] ?? null)
            ? trim($validated['size'])
            : null;

        if (!$color && !$size) {
            return back()->withErrors([
                'color' =>
                    'Please provide a color or size for the variant.',
            ]);
        }

        $duplicate = $product->variants()
            ->where('id', '!=', $variant->id)
            ->where(function ($query) use ($color) {
                if ($color === null) {
                    $query->whereNull('color');
                } else {
                    $query->where('color', $color);
                }
            })
            ->where(function ($query) use ($size) {
                if ($size === null) {
                    $query->whereNull('size');
                } else {
                    $query->where('size', $size);
                }
            })
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'color' =>
                    'This product variant already exists.',
            ]);
        }

        $variant->update([
            'color' => $color,
            'size' => $size,
            'stock' => $validated['stock'],
        ]);

        $product->update([
            'stock' => $product
                ->variants()
                ->sum('stock'),
        ]);

        return back()->with(
            'status',
            'Product variant updated successfully.'
        );
    }


    public function destroyVariant(
        Request $request,
        Product $product,
        ProductVariant $variant
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        abort_unless(
            $variant->product_id === $product->id,
            403
        );

        $variant->delete();

        $product->update([
            'stock' => $product
                ->variants()
                ->sum('stock'),
        ]);

        return back()->with(
            'status',
            'Product variant removed successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ARCHIVE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function archive(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        abort_if(
            $product->status === 'archived',
            422,
            'This product is already archived.'
        );

        $product->update([
            'status' => 'archived',
        ]);

        return redirect()
            ->route('seller.products')
            ->with(
                'status',
                'Product archived successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | RESTORE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function restore(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        abort_unless(
            $product->status === 'archived',
            422,
            'This product is not archived.'
        );

        $product->update([
            'status' => 'pending',
        ]);

        return redirect()
            ->route('seller.products')
            ->with(
                'status',
                'Product restored and submitted for admin approval.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT VALIDATION
    |--------------------------------------------------------------------------
    */

    private function validated(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * CRITICAL CATEGORY RULE
             *
             * The seller can only choose an active category.
             * Buyer Categories also only displays active categories.
             */
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(
                        fn ($query) =>
                            $query->where('is_active', true)
                    ),
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'old_price' => [
                'nullable',
                'numeric',
                'min:0',
                'gte:price',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'required',
                'in:draft,pending,inactive',
            ],

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVE CATEGORIES
    |--------------------------------------------------------------------------
    |
    | ONE SOURCE OF TRUTH:
    |
    | categories table
    |       ↓
    | Seller
    |       ↓
    | products.category_id
    |       ↓
    | Buyer
    |
    */

    private function activeCategories()
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | STORE PRODUCT IMAGES
    |--------------------------------------------------------------------------
    */

    private function storeProductImages(
        Request $request
    ): array {
        $imagePaths = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if (!$image->isValid()) {
                    continue;
                }

                $imagePaths[] = $image->store(
                    'products',
                    'public'
                );
            }
        }

        if (
            count($imagePaths) === 0 &&
            $request->hasFile('image')
        ) {
            $image = $request->file('image');

            if ($image->isValid()) {
                $imagePaths[] = $image->store(
                    'products',
                    'public'
                );
            }
        }

        return $imagePaths;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE PRODUCT VARIANTS
    |--------------------------------------------------------------------------
    */

    private function validateVariants(
        Request $request
    ): array {
        $variants = $request->validate([
            'variants' => [
                'nullable',
                'array',
            ],

            'variants.*.color' => [
                'nullable',
                'string',
                'max:100',
            ],

            'variants.*.size' => [
                'nullable',
                'string',
                'max:100',
            ],

            'variants.*.stock' => [
                'required',
                'integer',
                'min:0',
            ],
        ])['variants'] ?? [];

        $normalized = [];

        foreach ($variants as $index => $variant) {
            $color = filled($variant['color'] ?? null)
                ? trim($variant['color'])
                : null;

            $size = filled($variant['size'] ?? null)
                ? trim($variant['size'])
                : null;

            if (!$color && !$size) {
                abort(
                    422,
                    'Variant ' .
                    ($index + 1) .
                    ' must have a color or size.'
                );
            }

            $combinationKey =
                strtolower($color ?? '__none__') .
                '|' .
                strtolower($size ?? '__none__');

            if (
                collect($normalized)->contains(
                    fn ($existing) =>
                        $existing['_key'] === $combinationKey
                )
            ) {
                abort(
                    422,
                    'Duplicate product variant: ' .
                    ($color ?? 'No Color') .
                    ' / ' .
                    ($size ?? 'No Size') .
                    '.'
                );
            }

            $normalized[] = [
                'color' => $color,
                'size' => $size,
                'stock' => (int) $variant['stock'],
                '_key' => $combinationKey,
            ];
        }

        return collect($normalized)
            ->map(function ($variant) {
                unset($variant['_key']);

                return $variant;
            })
            ->values()
            ->all();
    }


    /*
    |--------------------------------------------------------------------------
    | UNIQUE PRODUCT SLUG
    |--------------------------------------------------------------------------
    */

    private function uniqueSlug(
        string $name
    ): string {
        $base = Str::slug($name) ?: 'product';

        $slug = $base;
        $number = 2;

        while (
            Product::where(
                'slug',
                $slug
            )->exists()
        ) {
            $slug =
                $base .
                '-' .
                $number++;
        }

        return $slug;
    }
}