<?php

namespace Modules\RealEstate\Database\Seeders;

use App\Models\RealEstatePropertyType;
use Illuminate\Database\Seeder;

class RealEstateModuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Biệt thự', 'biet-thu', 'fa-solid fa-house-chimney-window'],
            ['Nhà vườn', 'nha-vuon', 'fa-solid fa-tree-city'],
            ['Nhà phố', 'nha-pho', 'fa-solid fa-house'],
            ['Chung cư', 'chung-cu', 'fa-solid fa-building'],
            ['Căn hộ', 'can-ho', 'fa-regular fa-building'],
        ] as $index => [$name, $slug, $icon]) {
            RealEstatePropertyType::query()->updateOrCreate(
                ['website_key' => 'website-main', 'slug' => $slug],
                ['name' => $name, 'icon' => $icon, 'sort_order' => $index, 'is_active' => true],
            );
        }
    }
}
