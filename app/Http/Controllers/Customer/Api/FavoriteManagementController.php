<?php

namespace App\Http\Controllers\Customer\Api;

use App\Models\CustomerFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteManagementController
{
    public function destroy(Request $request, CustomerFavorite $favorite): JsonResponse
    {
        abort_unless($favorite->customer_id === $request->user('customer')?->id, 403);

        $favorite->delete();

        return response()->json([
            'message' => 'Đã xóa sản phẩm khỏi danh sách yêu thích.',
        ]);
    }
}