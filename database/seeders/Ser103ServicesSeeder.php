<?php

namespace Database\Seeders;

use App\Core\Themes\Demo\Ser103DemoContentProvider;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class Ser103ServicesSeeder extends Seeder
{
    public function run(): void
    {
        $websiteKeys = Site::query()->where('theme_key', 'SER103')->where('status', 'active')->pluck('website_key')
            ->merge(SiteProfile::query()->withoutGlobalScopes()->where('active_theme_key', 'SER103')->pluck('website_key'))
            ->unique();

        $seededWebsites = 0;
        foreach ($websiteKeys as $websiteKey) {
            $site = Site::query()->where('website_key', $websiteKey)->first();
            if ($site !== null && ($site->status !== 'active' || (filled($site->theme_key) && $site->theme_key !== 'SER103'))) {
                continue;
            }
            $counts = $this->seedForWebsite($websiteKey);
            $seededWebsites++;
            $this->command?->info($websiteKey.': '.json_encode($counts, JSON_UNESCAPED_UNICODE));
        }

        if ($seededWebsites === 0) {
            $this->command?->info('Không có website SER103 để bổ sung dịch vụ mẫu.');
        }
    }

    /** Add only missing services; leave existing articles, branding, and edits intact. */
    public function seedForWebsite(string $websiteKey): array
    {
        $context = app(SiteContext::class);
        $previousSite = $context->site();
        $previousWebsite = $context->websiteKey();
        $site = Site::query()->where('website_key', $websiteKey)->first();
        $profile = SiteProfile::query()->withoutGlobalScopes()->where('website_key', $websiteKey)->first();

        if (($site?->theme_key ?: $profile?->active_theme_key) !== 'SER103' || ($site !== null && $site->status !== 'active')) {
            throw new RuntimeException('Seeder này chỉ dành cho website đang dùng theme SER103.');
        }

        try {
            $context->set($site, $websiteKey);

            return DB::transaction(function (): array {
                $category = CmsServiceCategory::query()->firstOrCreate(['slug' => 'ser103-dich-vu-cuoi'], [
                    'name' => 'Dịch vụ cưới SER103',
                    'description' => 'Giải pháp trọn gói cho một ngày cưới thanh lịch và đáng nhớ.',
                    'sort_order' => 0, 'is_active' => true,
                ]);
                $counts = ['service_categories' => (int) $category->wasRecentlyCreated, 'services' => 0, 'service_images' => 0];
                if ($category->wasRecentlyCreated) {
                    $this->record($category);
                }

                foreach (Ser103DemoContentProvider::demoServices() as $index => [$title, $summary, $image, $icon]) {
                    $service = CmsService::query()->firstOrCreate(['slug' => Str::slug('ser103-'.$title)], [
                        'cms_service_category_id' => $category->id,
                        'title' => $title, 'summary' => $summary,
                        'content' => '<p>'.$summary.'</p><p>Đội ngũ Bøhu đồng hành từ ý tưởng đến ngày tổ chức để mọi chi tiết đều trọn vẹn.</p>',
                        'status' => 'published', 'publish_at' => now(),
                        'icon' => $icon, 'button_label' => 'Xem chi tiết',
                        'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index,
                    ]);
                    if (! $service->wasRecentlyCreated) {
                        continue;
                    }
                    $this->record($service);
                    $serviceImage = CmsServiceImage::query()->create([
                        'cms_service_id' => $service->id, 'image_url' => '/theme-demo/ser103/'.$image,
                        'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0,
                    ]);
                    $this->record($serviceImage);
                    $counts['services']++;
                    $counts['service_images']++;
                }

                return $counts;
            });
        } finally {
            $context->set($previousSite, $previousWebsite);
        }
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create([
            'theme_key' => 'SER103', 'preset_key' => 'ser103-bohu-wedding',
            'model_type' => $model::class, 'model_id' => $model->getKey(),
        ]);
    }
}
