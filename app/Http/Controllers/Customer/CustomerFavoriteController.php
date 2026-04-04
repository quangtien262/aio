<?php

namespace App\Http\Controllers\Customer;

use App\Models\CatalogProduct;
use App\Models\CustomerFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerFavoriteController
{
    public function __invoke(Request $request, CatalogProduct $product): RedirectResponse|JsonResponse
    {
        $customer = $request->user('customer');

        $favorite = CustomerFavorite::query()->firstWhere([
            'customer_id' => $customer?->id,
            'catalog_product_id' => $product->id,
        ]);

        $isFavorite = false;
        $message = 'Đã thêm sản phẩm vào danh sách yêu thích.';

        if ($favorite) {
            $favorite->delete();
            $message = 'Đã bỏ sản phẩm khỏi danh sách yêu thích.';
        } else {
            CustomerFavorite::query()->create([
                'customer_id' => $customer?->id,
                'catalog_product_id' => $product->id,
            ]);
            $isFavorite = true;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'is_favorite' => $isFavorite,
                ],
            ]);
        }

        return back()->with('cart_success', $message);
    }
}