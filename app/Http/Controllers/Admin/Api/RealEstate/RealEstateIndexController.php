<?php

namespace App\Http\Controllers\Admin\Api\RealEstate;

use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Support\RealEstate\RealEstateListingPresenter;
use Illuminate\Http\JsonResponse;

class RealEstateIndexController
{
    public function __invoke(): JsonResponse
    {
        $items = RealEstateListing::query()
            ->with(['propertyType', 'media'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('id')
            ->get()
            ->map(fn (RealEstateListing $listing): array => RealEstateListingPresenter::make($listing))
            ->values()
            ->all();

        $types = RealEstatePropertyType::query()
            ->withCount('listings')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (RealEstatePropertyType $type): array => [
                'id' => $type->id,
                'parent_id' => $type->parent_id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'icon' => $type->icon,
                'image_url' => $type->image_url,
                'sort_order' => (int) $type->sort_order,
                'is_active' => (bool) $type->is_active,
                'listings_count' => (int) $type->listings_count,
            ])
            ->values()
            ->all();

        return response()->json(['data' => [
            'items' => $items,
            'types' => $types,
            'total' => count($items),
            'metrics' => [
                'published' => RealEstateListing::query()->where('publication_status', 'published')->count(),
                'for_sale' => RealEstateListing::query()->where('transaction_type', 'sale')->count(),
                'for_rent' => RealEstateListing::query()->where('transaction_type', 'rent')->count(),
            ],
        ]]);
    }
}
