<?php

namespace Database\Seeders;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Nt501ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brief = json_decode(file_get_contents(resource_path('demo/remaining-themes.json')), true, 512, JSON_THROW_ON_ERROR)['NT501'];
        $websiteKeys = Site::query()->where('theme_key', 'NT501')->pluck('website_key')
            ->merge(SiteProfile::withoutGlobalScope('current_website')->where('active_theme_key', 'NT501')->pluck('website_key'))
            ->filter()->unique();

        DB::transaction(function () use ($brief, $websiteKeys): void {
            foreach ($websiteKeys as $websiteKey) {
                foreach ($brief['services'] as $index => $title) {
                    $summary = $brief['brand'].' hỗ trợ '.mb_strtolower($title).' với quy trình khảo sát, đề xuất phương án và kiểm tra kết quả rõ ràng.';
                    $service = CmsService::query()->forWebsite($websiteKey)->firstOrCreate([
                        'website_key' => $websiteKey,
                        'slug' => Str::slug('NT501-'.$title),
                    ], [
                        'title' => $title, 'summary' => $summary,
                        'content' => '<h2>'.e($title).'</h2><p>'.e($summary).'</p>',
                        'status' => 'published', 'publish_at' => now(),
                        'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index,
                    ]);
                    if ($service->wasRecentlyCreated) {
                        $this->mark($service, $websiteKey, $brief['preset']);
                        $image = CmsServiceImage::create([
                            'cms_service_id' => $service->id,
                            'image_url' => $brief['images'][$index % count($brief['images'])],
                            'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0,
                        ]);
                        $this->mark($image, $websiteKey, $brief['preset']);
                    }
                }

                foreach ($brief['catalog_categories'] as $index => $definition) {
                    $category = CatalogCategory::query()->forWebsite($websiteKey)->firstOrCreate([
                        'website_key' => $websiteKey,
                        'slug' => Str::slug('NT501-'.$definition['slug']),
                    ], [
                        'name' => $definition['name'], 'description' => $definition['description'],
                        'image_url' => $definition['image_url'], 'is_active' => true, 'sort_order' => $index,
                    ]);
                    if ($category->wasRecentlyCreated) {
                        $this->mark($category, $websiteKey, $brief['preset']);
                    }
                    foreach ($definition['products'] as $title) {
                        $productIndex = array_search($title, $brief['products'], true);
                        $product = CatalogProduct::query()->forWebsite($websiteKey)->firstOrCreate([
                            'website_key' => $websiteKey, 'slug' => Str::slug('NT501-'.$title),
                        ], [
                            'name' => $title, 'catalog_category_id' => $category->id,
                            'sku' => 'NT501-DEMO-'.($productIndex + 1),
                            'price' => 250000 * ($productIndex + 1), 'stock' => 20,
                            'short_description' => 'Sản phẩm mẫu tham khảo cho không gian nội thất.',
                            'image_url' => $brief['product_images'][$productIndex],
                            'is_active' => true, 'is_featured' => true, 'is_highlight' => true,
                            'sort_order' => $productIndex,
                        ]);
                        if ($product->wasRecentlyCreated) {
                            $this->mark($product, $websiteKey, $brief['preset']);
                        } elseif (ThemeDemoRecord::query()->forWebsite($websiteKey)
                            ->where('theme_key', 'NT501')->where('model_type', CatalogProduct::class)
                            ->where('model_id', $product->id)->exists()) {
                            // Move legacy demo products out of the generic category; preserve manual products.
                            $product->update(['catalog_category_id' => $category->id]);
                        }
                    }
                }
            }
        });
    }

    private function mark(Model $model, string $websiteKey, string $preset): void
    {
        ThemeDemoRecord::create([
            'website_key' => $websiteKey, 'theme_key' => 'NT501', 'preset_key' => $preset,
            'model_type' => $model::class, 'model_id' => $model->id,
        ]);
    }
}
