<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductComplianceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminComplianceController extends Controller
{
    /**
     * Display seller product compliance records.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with([
                'seller:id,name,email,store_name,status,rejection_reason',
                'category:id,name',
                'complianceReviews' => function ($reviewQuery) {
                    $reviewQuery
                        ->with('admin:id,name')
                        ->latest('reviewed_at');
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

        if (
            $request->filled('status') &&
            $request->input('status') !== 'all'
        ) {
            $query->where('status', $request->input('status'));
        }

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
            'title' => 'Seller compliance',
            'eyebrow' => 'Compliance',

            'description' =>
                'Review seller product listings, approve compliant products, flag prohibited content, and suspend sellers when necessary.',

            'active' => 'compliance',

            'products' => $products,

            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * Approve, flag, or suspend a product/seller.
     */
    public function updateStatus(
        Request $request,
        Product $product
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:approve,flag,suspend',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $action = $validated['action'];

        $note = trim(
            (string) ($validated['note'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | APPROVE PRODUCT
        |--------------------------------------------------------------------------
        */

        if ($action === 'approve') {
            DB::transaction(function () use ($product, $note) {
                $product->update([
                    'status' => 'approved',
                ]);

                ProductComplianceReview::create([
                    'product_id' => $product->id,
                    'admin_id' => auth()->id(),
                    'action' => 'approved',
                    'note' => $note !== '' ? $note : null,
                    'reviewed_at' => now(),
                ]);
            });

            return back()->with(
                'status',
                'Product approved and restored to the marketplace.'
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

        if (!$seller) {
            return back()->withErrors([
                'seller' =>
                    'The seller associated with this product could not be found.',
            ]);
        }

        if ($seller->usertype === 'admin') {
            return back()->withErrors([
                'seller' =>
                    'An administrator account cannot be suspended through product compliance.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate seller suspension records
        |--------------------------------------------------------------------------
        |
        | If the seller is already suspended, do NOT create another
        | seller_suspended review for another product.
        |
        */

        if ($seller->status === 'suspended') {
            return back()->withErrors([
                'seller' =>
                    'This seller is already suspended. Use the existing Unsuspend action to restore the seller account.',
            ]);
        }

        DB::transaction(function () use (
            $product,
            $seller,
            $note
        ) {
            /*
            | Suspend the seller account.
            */
            $seller->update([
                'status' => 'suspended',
            ]);

            /*
            | Hide the product that triggered the suspension.
            */
            $product->update([
                'status' => 'inactive',
            ]);

            /*
            | Record which product triggered the seller suspension.
            */
            ProductComplianceReview::create([
                'product_id' => $product->id,
                'admin_id' => auth()->id(),
                'action' => 'seller_suspended',
                'note' => $note !== '' ? $note : null,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with(
            'status',
            'Seller suspended and the selected product was removed from the active marketplace listing.'
        );
    }

    /**
     * Unsuspend the seller.
     */
    public function unsuspend(
        Product $product
    ): RedirectResponse {
        $seller = $product->seller;

        if (!$seller) {
            return back()->withErrors([
                'seller' =>
                    'The seller associated with this product could not be found.',
            ]);
        }

        if ($seller->usertype === 'admin') {
            return back()->withErrors([
                'seller' =>
                    'An administrator account cannot be unsuspended through product compliance.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure THIS product is the suspension trigger
        |--------------------------------------------------------------------------
        */

        $suspensionReview = $product->complianceReviews()
            ->where('action', 'seller_suspended')
            ->latest('reviewed_at')
            ->first();

        if (!$suspensionReview) {
            return back()->withErrors([
                'seller' =>
                    'This product is not the product associated with the seller suspension.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Seller must actually be suspended
        |--------------------------------------------------------------------------
        */

        if ($seller->status !== 'suspended') {
            return back()->withErrors([
                'seller' =>
                    'This seller is no longer suspended.',
            ]);
        }

        DB::transaction(function () use (
            $product,
            $seller
        ) {
            /*
            | Restore seller account.
            */
            $seller->update([
                'status' => 'approved',
            ]);

            /*
            | Record the unsuspension in compliance history.
            */
            ProductComplianceReview::create([
                'product_id' => $product->id,
                'admin_id' => auth()->id(),
                'action' => 'seller_unsuspended',
                'note' =>
                    'Seller account was unsuspended by an administrator.',
                'reviewed_at' => now(),
            ]);
        });

        return back()->with(
            'status',
            'Seller unsuspended successfully. The seller status is now approved.'
        );
    }
}