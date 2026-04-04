<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CmsMenu;
use Illuminate\Http\JsonResponse;

class MenuIndexController
{
    public function __invoke(CmsMenuLocationRegistry $locationRegistry): JsonResponse
    {
        $query = CmsMenu::query()->orderBy('location')->orderBy('name');

        $items = $query->get()->map(fn (CmsMenu $menu): array => [
            'id' => $menu->id,
            'name' => $menu->name,
            'location' => $menu->location,
            'items' => $menu->items ?? [],
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'locations' => $locationRegistry->all(),
            ],
        ]);
    }
}
