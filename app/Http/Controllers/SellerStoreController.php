<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
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
                'store_banner_path' => $seller->store_banner_path,
                'store_tagline' => $seller->store_tagline,
                'operating_hours' => $seller->operating_hours ?: User::getDefaultOperatingHours(),
                'response_time' => $seller->response_time ?: 'within_day',
                'shipping_policy' => $seller->shipping_policy,
                'warranty_policy' => $seller->warranty_policy,
                'return_policy' => $seller->return_policy,
                'vacation_mode' => (bool) $seller->vacation_mode,
                'vacation_message' => $seller->vacation_message,
                'status_info' => $seller->getStoreStatus(),

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
        $seller = $request->user();

        $data = $request->validate([
            'store_name' => [
                'required',
                'string',
                'max:150',
            ],
            'store_tagline' => [
                'nullable',
                'string',
                'max:255',
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
            'store_banner' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
            'remove_banner' => [
                'nullable',
                'boolean',
            ],
            'remove_logo' => [
                'nullable',
                'boolean',
            ],
            'operating_hours' => [
                'nullable',
                'array',
            ],
            'response_time' => [
                'nullable',
                'string',
                'in:within_hour,few_hours,same_day,next_day',
            ],
            'shipping_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'warranty_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'return_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'vacation_mode' => [
                'nullable',
                'boolean',
            ],
            'vacation_message' => [
                'nullable',
                'string',
                'max:1000',
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
        } elseif (!empty($data['remove_logo'])) {
            $data['store_logo_path'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Store Banner (SELLER-62)
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('store_banner')) {
            $data['store_banner_path'] = $request
                ->file('store_banner')
                ->store('store-banners', 'public');
        } elseif (!empty($data['remove_banner'])) {
            $data['store_banner_path'] = null;
        }

        unset($data['store_logo'], $data['store_banner'], $data['remove_banner'], $data['remove_logo']);

        /*
        |--------------------------------------------------------------------------
        | Update Seller Record
        |--------------------------------------------------------------------------
        */

        $seller->update($data);

        return back()->with(
            'status',
            'Store settings and branding updated successfully.'
        );
    }
}