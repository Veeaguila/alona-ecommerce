<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $recentAdjustments = StockAdjustment::query()
            ->where('seller_id', $seller->id)
            ->with([
                'product:id,name,image_path',
                'variant:id,product_id,color,size',
                'user:id,name',
            ])
            ->latest()
            ->take(25)
            ->get();

        return Inertia::render(
            'Seller/StockMonitoring',
            [
                'products' => $products,
                'summary' => $summary,
                'recent_adjustments' => $recentAdjustments,

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

        if ($request->user()->status !== 'approved' && $request->input('status') !== 'draft') {
            return back()->withErrors([
                'status' => 'Your store account must be approved before you can publish products.',
            ]);
        }

        if ($request->input('status') === 'draft') {
            $validated['status'] = 'draft';
        } elseif ($request->input('status') === 'inactive') {
            $validated['status'] = 'inactive';
        } else {
            $validated['status'] = 'pending';
        }

        $imagePaths = $this->storeProductImages($request);

        if (count($imagePaths) > 0) {
            $validated['image_path'] = $imagePaths[0];
        }

        unset(
            $validated['image'],
            $validated['images']
        );

        $idempotencyKey = 'prod_publish_' . $request->user()->id . '_' . md5((string) $request->input('name') . '_' . (string) $request->input('price'));
        if (\Illuminate\Support\Facades\Cache::has($idempotencyKey)) {
            return redirect()->route('seller.products')->with('status', 'Product created successfully.');
        }

        try {
            DB::transaction(function () use (
                $validated,
                $variants,
                $imagePaths,
                $request,
                $idempotencyKey
            ) {
                $product = Product::create($validated);
                \Illuminate\Support\Facades\Cache::put($idempotencyKey, $product->id, now()->addSeconds(3));

                if ($request->has('store_category_ids') && is_array($request->store_category_ids)) {
                    $product->storeCategories()->sync($request->store_category_ids);
                } elseif ($request->filled('store_category_id')) {
                    $product->storeCategories()->sync([$request->input('store_category_id')]);
                }

                foreach ($imagePaths as $index => $imagePath) {
                    $product->images()->create([
                        'image_path' => $imagePath,
                        'sort_order' => $index,
                    ]);
                }

                foreach ($variants as $variantData) {
                    $product->variants()->create([
                        'color' => $variantData['color'] ?? null,
                        'size' => $variantData['size'] ?? null,
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

        $validated = $this->validated($request, $product);

        if ($request->has('store_category_ids') && is_array($request->store_category_ids)) {
            $product->storeCategories()->sync($request->store_category_ids);
        }

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

        if ($request->filled('status')) {
            $product->update([
                'status' => $request->input('status'),
            ]);
        } elseif ($product->status !== 'approved') {
            $product->update([
                'status' => 'pending',
            ]);
        }

        $freshProduct = $product->fresh();
        if ($freshProduct && $freshProduct->isLowStock()) {
            \App\Services\SellerNotificationService::sendLowStockAlert($freshProduct);
        }

        return back()->with(
            'status',
            'Product updated successfully.'
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

        $typeFilter = $request->input('type', 'all');

        $adjustments = $product->stockAdjustments()
            ->with([
                'variant:id,product_id,color,size',
                'user:id,name,email',
            ])
            ->when(
                $typeFilter && $typeFilter !== 'all',
                fn ($q) => $q->where('type', $typeFilter)
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Seller/Inventory', [
            'product' => $product,
            'adjustments' => $adjustments,
            'filters' => [
                'type' => $typeFilter,
            ],
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
        $seller = $request->user();

        abort_unless(
            $product->seller_id === $seller->id,
            403
        );

        $validated = $request->validate([
            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'type' => [
                'nullable',
                'string',
                Rule::in(['stock_in', 'stock_out', 'damage', 'return', 'manual']),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:100',
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

            'variants.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $product,
            $seller
        ) {
            $defaultType = $validated['type'] ?? null;
            $defaultNotes = $validated['notes'] ?? null;
            $refNumber = $validated['reference_number'] ?? null;

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

                $beforeStock = (int) $variant->stock;
                $afterStock = (int) $variantData['stock'];
                $diff = $afterStock - $beforeStock;

                if ($diff !== 0) {
                    $variant->update([
                        'stock' => $afterStock,
                    ]);

                    $itemType = $defaultType ?? ($diff > 0 ? 'stock_in' : 'stock_out');
                    $itemNotes = $variantData['notes'] ?? $defaultNotes ?? ($diff > 0 ? 'Stock replenished via inventory' : 'Stock adjusted via inventory');

                    StockAdjustment::create([
                        'seller_id' => $seller->id,
                        'user_id' => $seller->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'type' => $itemType,
                        'quantity' => $diff,
                        'before_stock' => $beforeStock,
                        'after_stock' => $afterStock,
                        'notes' => $itemNotes,
                        'reference_number' => $refNumber,
                    ]);
                }
            }

            if ($product->variants()->exists()) {
                $totalStock = $product
                    ->variants()
                    ->sum('stock');

                $product->update([
                    'stock' => $totalStock,
                ]);
            } else {
                $beforeStock = (int) $product->stock;
                $afterStock = (int) $validated['stock'];
                $diff = $afterStock - $beforeStock;

                if ($diff !== 0) {
                    $product->update([
                        'stock' => $afterStock,
                    ]);

                    $itemType = $defaultType ?? ($diff > 0 ? 'stock_in' : 'stock_out');
                    $itemNotes = $defaultNotes ?? ($diff > 0 ? 'Stock replenished via inventory' : 'Stock adjusted via inventory');

                    StockAdjustment::create([
                        'seller_id' => $seller->id,
                        'user_id' => $seller->id,
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'type' => $itemType,
                        'quantity' => $diff,
                        'before_stock' => $beforeStock,
                        'after_stock' => $afterStock,
                        'notes' => $itemNotes,
                        'reference_number' => $refNumber,
                    ]);
                }
            }
        });

        $freshProduct = $product->fresh();
        if ($freshProduct && $freshProduct->isLowStock()) {
            \App\Services\SellerNotificationService::sendLowStockAlert($freshProduct);
        }

        return back()->with(
            'status',
            'Inventory updated and logged successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STOCK ADJUSTMENT AUDIT LOGGING (SELLER-13)
    |--------------------------------------------------------------------------
    */

    public function adjustStock(
        Request $request,
        Product $product
    ): RedirectResponse {
        $seller = $request->user();

        abort_unless(
            $product->seller_id === $seller->id,
            403
        );

        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in(['stock_in', 'stock_out', 'damage', 'return', 'manual']),
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'mode' => [
                'nullable',
                'string',
                Rule::in(['add', 'subtract', 'set']),
            ],
            'product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        return DB::transaction(function () use ($validated, $product, $seller) {
            $variant = null;
            if (!empty($validated['product_variant_id'])) {
                $variant = $product->variants()
                    ->whereKey($validated['product_variant_id'])
                    ->first();

                abort_unless($variant, 403, 'Variant not found for this product.');
            } elseif ($product->variants()->exists()) {
                return back()->withErrors([
                    'product_variant_id' => 'Please select a variant for this product.',
                ]);
            }

            $currentStock = $variant ? (int) $variant->stock : (int) $product->stock;
            $type = $validated['type'];
            $qty = (int) $validated['quantity'];
            $mode = $validated['mode'] ?? null;

            if ($type === 'stock_in' || $type === 'return') {
                $delta = $qty;
                $newStock = $currentStock + $qty;
            } elseif ($type === 'damage' || $type === 'stock_out') {
                if ($qty > $currentStock) {
                    return back()->withErrors([
                        'quantity' => "Cannot deduct {$qty} items. Current stock is only {$currentStock}.",
                    ]);
                }
                $delta = -$qty;
                $newStock = $currentStock - $qty;
            } else { // manual
                if ($mode === 'subtract') {
                    if ($qty > $currentStock) {
                        return back()->withErrors([
                            'quantity' => "Cannot deduct {$qty} items. Current stock is only {$currentStock}.",
                        ]);
                    }
                    $delta = -$qty;
                    $newStock = $currentStock - $qty;
                } elseif ($mode === 'set') {
                    $newStock = $qty;
                    $delta = $newStock - $currentStock;
                } else { // add
                    $delta = $qty;
                    $newStock = $currentStock + $qty;
                }
            }

            if ($variant) {
                $variant->update(['stock' => $newStock]);
                $product->update([
                    'stock' => $product->variants()->sum('stock'),
                ]);
            } else {
                $product->update(['stock' => $newStock]);
            }

            StockAdjustment::create([
                'seller_id' => $seller->id,
                'user_id' => $seller->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'type' => $type,
                'quantity' => $delta,
                'before_stock' => $currentStock,
                'after_stock' => $newStock,
                'notes' => $validated['notes'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
            ]);

            return back()->with(
                'status',
                'Stock adjustment logged successfully.'
            );
        });
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
                'color' => 'Please provide at least a color or a size.',
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
                'color' => 'This product variant combination already exists.',
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
                'color' => 'Please provide at least a color or a size.',
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
                'color' => 'This product variant combination already exists.',
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
    | DELETE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        // SEL-PROD-014: Block deletion if product has unfinished orders
        $hasUnfinishedOrders = $product->orderItems()
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->exists();

        if ($hasUnfinishedOrders) {
            return back()->withErrors([
                'error' => 'Cannot delete a product with unfinished orders.',
            ])->with('error', 'Cannot delete a product with unfinished orders.');
        }

        // SEL-PROD-015: If product has only finished orders, archive it so historical order views still show product name, image and price paid
        if ($product->orderItems()->exists()) {
            $product->update(['status' => 'archived']);

            return redirect()
                ->route('seller.products')
                ->with('status', 'Product deleted permanently.');
        }

        try {
            DB::transaction(function () use ($product) {
                $imagePaths = $product->images()->pluck('image_path')->filter()->all();
                if ($product->image_path) {
                    $imagePaths[] = $product->image_path;
                }
                foreach (array_unique($imagePaths) as $path) {
                    Storage::disk('public')->delete($path);
                }

                $product->images()->delete();
                $product->variants()->delete();
                $product->questions()->delete();
                $product->reviews()->delete();
                $product->storeCategories()->detach();
                $product->delete();
            });

            return redirect()
                ->route('seller.products')
                ->with('status', 'Product deleted permanently.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete product: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE PRODUCT (SEL-PROD-012)
    |--------------------------------------------------------------------------
    */

    public function duplicate(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        $copy = $product->replicate([
            'rating',
            'reviews_count',
        ]);
        $copy->slug = $this->uniqueSlug($product->name);
        $copy->status = 'draft';
        $copy->save();

        foreach ($product->images as $img) {
            $copy->images()->create([
                'image_path' => $img->image_path,
                'sort_order' => $img->sort_order,
            ]);
        }

        foreach ($product->variants as $variant) {
            $copy->variants()->create([
                'color' => $variant->color,
                'size' => $variant->size,
                'stock' => $variant->stock,
            ]);
        }

        if ($product->storeCategories()->exists()) {
            $copy->storeCategories()->sync($product->storeCategories->pluck('id'));
        }

        return redirect()
            ->route('seller.products')
            ->with('status', 'Product duplicated as draft successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE / UPDATE PRODUCT STATUS (SEL-PROD-011)
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->seller_id === $request->user()->id,
            403
        );

        $newStatus = $request->input('status');
        if (! $newStatus) {
            $newStatus = $product->status === 'inactive' ? 'approved' : 'inactive';
        }

        $product->update(['status' => $newStatus]);

        return back()->with('status', 'Product status updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | BULK DEACTIVATE (SEL-PROD-016)
    |--------------------------------------------------------------------------
    */

    public function bulkDeactivate(Request $request): RedirectResponse
    {
        $productIds = $request->input('product_ids', []);
        if (is_array($productIds) && count($productIds) > 0) {
            Product::where('seller_id', $request->user()->id)
                ->whereIn('id', $productIds)
                ->update(['status' => 'inactive']);
        }

        return redirect()
            ->route('seller.products')
            ->with('status', 'Selected products deactivated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | BULK DELETE (SEL-PROD-016)
    |--------------------------------------------------------------------------
    */

    public function bulkDelete(Request $request): RedirectResponse
    {
        $productIds = $request->input('product_ids', []);
        $deletedCount = 0;
        $skipped = [];

        if (is_array($productIds)) {
            $products = Product::where('seller_id', $request->user()->id)
                ->whereIn('id', $productIds)
                ->get();

            foreach ($products as $product) {
                $hasUnfinishedOrders = $product->orderItems()
                    ->whereHas('order', function ($q) {
                        $q->whereNotIn('status', ['completed', 'cancelled']);
                    })
                    ->exists();

                if ($hasUnfinishedOrders) {
                    $skipped[] = $product->name;
                    continue;
                }

                if ($product->orderItems()->exists()) {
                    $product->update(['status' => 'archived']);
                } else {
                    $imagePaths = $product->images()->pluck('image_path')->filter()->all();
                    if ($product->image_path) {
                        $imagePaths[] = $product->image_path;
                    }
                    foreach (array_unique($imagePaths) as $path) {
                        Storage::disk('public')->delete($path);
                    }
                    $product->images()->delete();
                    $product->variants()->delete();
                    $product->questions()->delete();
                    $product->reviews()->delete();
                    $product->storeCategories()->detach();
                    $product->delete();
                }
                $deletedCount++;
            }
        }

        $msg = "Deleted {$deletedCount} product(s).";
        if (! empty($skipped)) {
            $msg .= ' ' . implode(', ', $skipped) . ' was skipped because it has unfinished orders.';
        }

        return redirect()->route('seller.products')->with('status', $msg);
    }





    /*
    |--------------------------------------------------------------------------
    | PRODUCT VALIDATION
    |--------------------------------------------------------------------------
    */

    private function validated(
        Request $request,
        ?Product $product = null
    ): array {
        $status = $request->input('status', 'pending');
        $isDraft = $status === 'draft';

        if ($isDraft) {
            return $request->validate([
                'name' => [
                    'required',
                    'string',
                    'min:1',
                    'max:120',
                ],
                'category_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('categories', 'id')->where(
                        fn ($query) => $query->where('is_active', true)
                    ),
                ],
                'price' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:1000000.00',
                ],
                'old_price' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'stock' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:99999',
                ],
                'weight' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'length' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'width' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'height' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'brand' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'condition' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
                'low_stock_threshold' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:1000',
                ],
                'description' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
                'status' => [
                    'required',
                    'in:draft,pending,inactive,approved,active',
                ],
                'images' => [
                    'nullable',
                    'array',
                    'max:9',
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

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'min:5',
                'max:120',
            ],
            'description' => [
                'required',
                'string',
                'min:20',
                'max:3000',
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'price' => [
                'required',
                'numeric',
                'min:1.00',
                'max:1000000.00',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'old_price' => [
                'nullable',
                'numeric',
                'min:1.00',
                'gt:price',
            ],
            'stock' => [
                'required',
                'integer',
                'min:0',
                'max:99999',
            ],
            'weight' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'length' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
            'width' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
            'height' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
            'brand' => [
                'nullable',
                'string',
                'max:100',
            ],
            'condition' => [
                'nullable',
                'string',
                'max:20',
            ],
            'low_stock_threshold' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
            'status' => [
                'required',
                'in:draft,pending,inactive,approved,active',
            ],
            'images' => [
                'nullable',
                'array',
                'max:9',
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
        ], [
            'name.required' => 'Product name is required.',
            'name.min' => 'Product name must be at least 5 characters.',
            'name.max' => 'Product name cannot exceed 120 characters.',
            'description.required' => 'Product description is required.',
            'description.min' => 'Product description must be at least 20 characters.',
            'description.max' => 'Product description cannot exceed 3,000 characters.',
            'category_id.required' => 'Platform category is required.',
            'price.required' => 'Product price is required.',
            'price.min' => 'Price must be at least ₱1.00.',
            'price.max' => 'Price cannot exceed ₱1,000,000.00.',
            'price.regex' => 'Price cannot have more than 2 decimal places.',
            'stock.required' => 'Product stock is required.',
            'stock.min' => 'Stock cannot be negative.',
            'stock.max' => 'Stock cannot exceed 99,999.',
            'weight.required' => 'Product weight is required.',
            'weight.min' => 'Weight must be at least 0.01 kg.',
            'images.*.max' => 'Each image must not exceed 5 MB (5,120 KB).',
            'images.*.mimes' => 'Only JPG, JPEG, PNG, and WEBP image files are accepted.',
            'image.max' => 'The image must not exceed 5 MB (5,120 KB).',
            'image.mimes' => 'Only JPG, JPEG, PNG, and WEBP image files are accepted.',
        ]);

        $validator->after(function ($v) use ($request, $product) {
            // Check 3+ decimal places on price before numeric cast (SEL-PROD-004)
            if ($request->has('price') && $request->input('price') !== '' && $request->input('price') !== null) {
                $rawPrice = (string) $request->input('price');
                if (is_numeric($rawPrice) && strpos($rawPrice, '.') !== false) {
                    $decimals = strlen(substr(strrchr($rawPrice, '.'), 1));
                    if ($decimals > 2) {
                        $v->errors()->add('price', 'Price cannot have more than 2 decimal places.');
                    }
                }
            }

            // Validate dimensions (SEL-PROD-008: Dimensions 0x0x0 or abc rejected)
            if ($request->filled('length') || $request->filled('width') || $request->filled('height')) {
                $l = $request->input('length');
                $w = $request->input('width');
                $h = $request->input('height');
                if (($l !== null && $l !== '' && (! is_numeric($l) || (float) $l <= 0)) ||
                    ($w !== null && $w !== '' && (! is_numeric($w) || (float) $w <= 0)) ||
                    ($h !== null && $h !== '' && (! is_numeric($h) || (float) $h <= 0))) {
                    $v->errors()->add('dimensions', 'Dimensions must be positive numeric values greater than zero.');
                }
            }

            // Check image count limits (SEL-PROD-006: 0 images reject, max 9 images)
            $newFilesCount = 0;
            if ($request->hasFile('images')) {
                $newFilesCount += count($request->file('images'));
            }
            if ($request->hasFile('image')) {
                $newFilesCount += 1;
            }

            $existingCount = $product ? max($product->images()->count(), ! empty($product->image_path) ? 1 : 0) : 0;
            $totalImages = $newFilesCount + $existingCount;

            if ($totalImages === 0) {
                $v->errors()->add('images', 'At least one product image is required to publish.');
            } elseif ($totalImages > 9) {
                $v->errors()->add('images', 'A maximum of 9 images is allowed.');
            }
        });

        return $validator->validate();
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

            $combinationKey = ($color ?? '__none__') . '|' . ($size ?? '__none__');

            if (
                collect($normalized)->contains(
                    fn ($existing) =>
                        $existing['_key'] === $combinationKey
                )
            ) {
                abort(
                    422,
                    'Duplicate product variant combination at variant ' .
                    ($index + 1) .
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