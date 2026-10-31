<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Models\ProductComplianceReview;
use App\Models\SellerNotification;
use App\Services\AdminNotificationService;
use App\Services\AiProductVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminComplianceController extends Controller
{
    /**
     * Display seller product compliance records with AI verification diagnostics.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with([
                'seller:id,name,email,store_name,status,rejection_reason',
                'category:id,name',
                'images' => function ($imgQuery) {
                    $imgQuery->orderBy('sort_order');
                },
                'complianceReviews' => function ($reviewQuery) {
                    $reviewQuery
                        ->with('admin:id,name')
                        ->latest('reviewed_at');
                },
                'adminNotifications' => function ($notifQuery) {
                    $notifQuery->latest();
                },
            ])
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ai_verification_explanation', 'like', "%{$search}%")

                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('store_name', 'like', "%{$search}%");
                    })

                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        /*
        |--------------------------------------------------------------------------
        | Approval Method Filter (Automatic vs Manual)
        |--------------------------------------------------------------------------
        */

        if ($request->filled('approval_method') && $request->input('approval_method') !== 'all') {
            $query->where('approval_method', $request->input('approval_method'));
        }

        /*
        |--------------------------------------------------------------------------
        | AI Verification Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('ai_status') && $request->input('ai_status') !== 'all') {
            $query->where('ai_verification_status', $request->input('ai_status'));
        }

        /*
        |--------------------------------------------------------------------------
        | Metrics Summary
        |--------------------------------------------------------------------------
        */

        $metrics = [
            'total' => Product::count(),
            'auto_approved' => Product::where('approval_method', 'automatic')->count(),
            'pending_review' => Product::where('status', 'pending')->count(),
            'ai_failed' => Product::whereIn('ai_verification_status', ['failed', 'uncertain', 'unclear_image', 'service_error'])->count(),
            'flagged' => Product::where('status', 'inactive')->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $products = $query
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Admin/Compliance', [
            'title' => 'Seller Compliance & AI Verification',
            'eyebrow' => 'Catalog Quality Assurance',

            'description' =>
                'Review marketplace listings, monitor AI automatic approval outcomes, inspect vision mismatches, and manage compliance decisions.',

            'active' => 'compliance',

            'products' => $products,
            'metrics' => $metrics,

            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', 'all'),
                'approval_method' => (string) $request->input('approval_method', 'all'),
                'ai_status' => (string) $request->input('ai_status', 'all'),
            ],
        ]);
    }

    /**
     * Approve, reject, flag, or suspend a product/seller.
     */
    public function updateStatus(
        Request $request,
        Product $product
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:approve,reject,flag,suspend',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $action = $validated['action'];
        $note = trim((string) ($validated['note'] ?? ''));

        if ($action === 'reject' && $note === '') {
            return back()->withErrors([
                'note' => 'Please provide a clear rejection reason explaining why this product listing was rejected.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVE PRODUCT (Manual Admin Approval)
        |--------------------------------------------------------------------------
        */

        if ($action === 'approve') {
            DB::transaction(function () use ($product, $note) {
                $product->update([
                    'status' => 'approved',
                    'approval_method' => 'manual',
                    'rejection_reason' => null,
                ]);

                ProductComplianceReview::create([
                    'product_id' => $product->id,
                    'admin_id' => auth()->id(),
                    'action' => 'approved',
                    'note' => $note !== '' ? $note : 'Manually approved by administrator.',
                    'reviewed_at' => now(),
                ]);

                AdminAuditLog::record(
                    auth()->id() ?? 1,
                    'product_manually_approved',
                    "Admin manually approved product '{$product->name}' (ID: #{$product->id})",
                    $product,
                    ['action' => 'approve', 'note' => $note]
                );
            });

            // Resolve admin notifications
            app(AdminNotificationService::class)->resolveProductNotifications($product);

            // Notify seller
            if ($product->seller_id) {
                SellerNotification::create([
                    'user_id' => $product->seller_id,
                    'type' => 'product_approved',
                    'title' => 'Product Approved',
                    'message' => "Your product '{$product->name}' has been reviewed and approved by an administrator.",
                    'url' => route('seller.products'),
                ]);
            }

            return back()->with(
                'status',
                "Product '{$product->name}' has been manually approved and published to the marketplace."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | REJECT PRODUCT
        |--------------------------------------------------------------------------
        */

        if ($action === 'reject') {
            DB::transaction(function () use ($product, $note) {
                $product->update([
                    'status' => 'inactive',
                    'approval_method' => 'manual',
                    'rejection_reason' => $note,
                ]);

                ProductComplianceReview::create([
                    'product_id' => $product->id,
                    'admin_id' => auth()->id(),
                    'action' => 'rejected',
                    'note' => $note,
                    'reviewed_at' => now(),
                ]);

                AdminAuditLog::record(
                    auth()->id() ?? 1,
                    'product_rejected',
                    "Admin rejected product '{$product->name}' (ID: #{$product->id}). Reason: {$note}",
                    $product,
                    ['action' => 'reject', 'reason' => $note]
                );
            });

            // Resolve admin notifications
            app(AdminNotificationService::class)->resolveProductNotifications($product);

            // Notify seller with rejection reason
            if ($product->seller_id) {
                SellerNotification::create([
                    'user_id' => $product->seller_id,
                    'type' => 'product_rejected',
                    'title' => 'Product Listing Rejected',
                    'message' => "Your product listing '{$product->name}' was rejected during compliance review. Reason: {$note}",
                    'url' => route('seller.products'),
                ]);
            }

            return back()->with(
                'status',
                "Product '{$product->name}' was rejected and removed from marketplace listing."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FLAG PRODUCT
        |--------------------------------------------------------------------------
        */

        if ($action === 'flag') {
            DB::transaction(function () use ($product, $note) {
                $product->update([
                    'status' => 'inactive',
                ]);

                ProductComplianceReview::create([
                    'product_id' => $product->id,
                    'admin_id' => auth()->id(),
                    'action' => 'flagged',
                    'note' => $note !== '' ? $note : null,
                    'reviewed_at' => now(),
                ]);

                AdminAuditLog::record(
                    auth()->id() ?? 1,
                    'product_flagged',
                    "Admin flagged product '{$product->name}' (ID: #{$product->id})",
                    $product,
                    ['action' => 'flag', 'note' => $note]
                );
            });

            return back()->with(
                'status',
                'Product flagged and hidden from buyers pending review.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SUSPEND SELLER
        |--------------------------------------------------------------------------
        */

        $seller = $product->seller;

        if (! $seller) {
            return back()->withErrors([
                'seller' => 'The seller associated with this product could not be found.',
            ]);
        }

        if ($seller->usertype === 'admin') {
            return back()->withErrors([
                'seller' => 'An administrator account cannot be suspended through product compliance.',
            ]);
        }

        if ($seller->status === 'suspended') {
            return back()->withErrors([
                'seller' => 'This seller is already suspended. Use the existing Unsuspend action to restore the seller account.',
            ]);
        }

        DB::transaction(function () use ($product, $seller, $note) {
            $seller->update([
                'status' => 'suspended',
            ]);

            $product->update([
                'status' => 'inactive',
            ]);

            ProductComplianceReview::create([
                'product_id' => $product->id,
                'admin_id' => auth()->id(),
                'action' => 'seller_suspended',
                'note' => $note !== '' ? $note : null,
                'reviewed_at' => now(),
            ]);

            AdminAuditLog::record(
                auth()->id() ?? 1,
                'seller_suspended',
                "Admin suspended seller '{$seller->name}' (Store: {$seller->store_name}) via product #{$product->id}",
                $seller,
                ['product_id' => $product->id, 'note' => $note]
            );
        });

        return back()->with(
            'status',
            'Seller suspended and the selected product was removed from the active marketplace listing.'
        );
    }

    /**
     * Unsuspend a previously suspended seller.
     */
    public function unsuspend(Product $product): RedirectResponse
    {
        $seller = $product->seller;

        if (! $seller) {
            return back()->withErrors([
                'seller' => 'The seller associated with this product could not be found.',
            ]);
        }

        if ($seller->status !== 'suspended') {
            return back()->withErrors([
                'seller' => 'This seller is not currently suspended.',
            ]);
        }

        DB::transaction(function () use ($product, $seller) {
            $seller->update([
                'status' => 'approved',
            ]);

            ProductComplianceReview::create([
                'product_id' => $product->id,
                'admin_id' => auth()->id(),
                'action' => 'seller_unsuspended',
                'note' => 'Seller restored by compliance administrator.',
                'reviewed_at' => now(),
            ]);

            AdminAuditLog::record(
                auth()->id() ?? 1,
                'seller_unsuspended',
                "Admin unsuspended seller '{$seller->name}' (Store: {$seller->store_name})",
                $seller
            );
        });

        return back()->with(
            'status',
            'Seller unsuspended successfully. The seller status is now approved.'
        );
    }

    /**
     * Re-run AI verification on demand for a product.
     */
    public function retryAiVerification(
        Request $request,
        Product $product
    ): RedirectResponse {
        if (! app()->environment('testing') && function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $verification = app(AiProductVerificationService::class)->verifyAndProcess($product);

        if ($verification['approved'] ?? false) {
            return back()->with(
                'status',
                "AI verification passed! Product '{$product->name}' was automatically approved and published."
            );
        }

        $explanation = $verification['explanation'] ?? 'Automatic approval failed.';

        return back()->with(
            'status',
            "AI verification completed: Product remains pending manual review. Outcome: {$explanation}"
        );
    }
}
