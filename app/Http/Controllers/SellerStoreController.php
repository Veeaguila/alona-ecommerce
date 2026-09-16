<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerStoreController extends Controller
{
    public function edit(Request $request): Response
    {
        $seller = $request->user();

        return Inertia::render('Seller/Store', [
            'store' => [
                'store_name' => $seller->store_name,
                'store_description' => $seller->store_description,
                'store_logo_path' => $seller->store_logo_path,

                'rating' => round(
                    (float) Product::where('seller_id', $seller->id)->avg('rating'),
                    2
                ),

                'products_count' => Product::where(
                    'seller_id',
                    $seller->id
                )->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => [
                'required',
                'string',
                'max:150',
            ],

            'store_description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'store_logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Store Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('store_logo')) {
            $data['store_logo_path'] = $request
                ->file('store_logo')
                ->store('store-logos', 'public');
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Uploaded File Object
        |--------------------------------------------------------------------------
        */

        unset($data['store_logo']);

        /*
        |--------------------------------------------------------------------------
        | Update Seller
        |--------------------------------------------------------------------------
        */

        $request->user()->update($data);

        return back()->with(
            'status',
            'Store profile updated successfully.'
        );
    }
}