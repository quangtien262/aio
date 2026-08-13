<?php

namespace App\Core\Themes\Demo;

use App\Core\Cms\CmsMenuLocalization;
use App\Enums\TranslationStatus;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\LocalizedRoute;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LandingPageLocalization;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class News88DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'NEWS88';

    private const PRESET_KEY = 'news88-editorial';

    public function __construct(
        private readonly LandingPageBuilder $builder,
        private readonly SiteContext $siteContext,
        private readonly LocalizedContentRepository $localizedContent,
        private readonly CmsMenuLocalization $menuLocalization,
        private readonly LandingPageLocalization $landingLocalization,
    ) {}

    public function themeKey(): string
    {
        return self::THEME_KEY;
    }

    public function defaultPreset(): string
    {
        return self::PRESET_KEY;
    }

    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'NEWS88 Editorial', 'description' => 'Cổng tin tức đa chuyên mục với dữ liệu CMS và giao diện song ngữ.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho NEWS88.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                'health' => ['Sức Khỏe', 'Health'],
                'sport' => ['Thể Thao', 'Sports'],
                'world' => ['Thế Giới', 'World'],
                'fashion' => ['Thời Trang', 'Fashion'],
                'digital' => ['Số Hóa', 'Digital'],
                'car' => ['Xe', 'Motoring'],
                'travel' => ['Du Lịch', 'Travel'],
                'entertainment' => ['Giải Trí', 'Entertainment'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $key => [$vi, $en]) {
                $category = CmsCategory::query()->create([
                    'name' => $vi,
                    'slug' => 'news88-'.$key,
                    'description' => 'Những thông tin mới và đáng chú ý trong chuyên mục '.$vi.'.',
                ]);
                $this->record($category);
                $this->publishTranslation($category, 'cms_category', ['name' => $en, 'slug' => 'news88-'.$key.'-en', 'description' => 'Fresh and useful stories from '.$en.'.']);
                $categories[$key] = $category;
            }

            $posts = [
                ['travel', 'Cù lao được mệnh danh “Vương quốc bần” miền Tây', 'The Mekong island known as the kingdom of mangroves', '/theme-demo/news88/hero-mekong.png', true],
                ['world', '28 ngày du lịch bụi một mình qua những miền đất mới', '28 days of solo travel across remarkable new lands', '/theme-demo/news88/travel-mountain.webp', true],
                ['world', 'Những tín hiệu mới từ kinh tế toàn cầu', 'New signals from the global economy', '/theme-demo/news88/digital.webp', true],
                ['entertainment', 'Những bước catwalk ấn tượng của mùa thời trang', 'The season’s most memorable catwalk moments', '/theme-demo/news88/entertainment.webp', true],
                ['car', 'Thị trường xe tiếp tục có nhiều điều chỉnh đáng chú ý', 'The car market sees another notable shift', '/theme-demo/news88/vehicle.webp', true],
                ['health', 'Những thói quen khi ăn sáng không khoa học cần thay đổi', 'Breakfast habits worth changing for better health', '/theme-demo/ec903/food-brunch.webp', false],
                ['health', 'Một số sai lầm trong bảo vệ cơ thể vào những ngày lạnh giá', 'Common mistakes when protecting your body in cold weather', '/theme-demo/news88/lifestyle.webp', false],
                ['health', 'Cách uống trà an toàn cho thận và dạ dày', 'How to drink tea safely for your kidneys and stomach', '/theme-demo/ec903/food-dessert.webp', false],
                ['health', 'Kinh nghiệm chăm sóc sức khỏe răng miệng tự nhiên', 'Practical tips for natural oral care', '/theme-demo/news88/health-grid.png', false],
                ['health', 'Chế độ dinh dưỡng cân bằng cho người bận rộn', 'Balanced nutrition for busy people', '/theme-demo/ec906/nutrition.png', false],
                ['health', 'Ba thời điểm vàng để vận động mỗi ngày', 'Three ideal times to exercise every day', '/theme-demo/news88/health-main.png', false],
                ['car', 'Mẫu SUV mới tạo sức hút nhờ gói công nghệ an toàn', 'A new SUV draws attention with its safety technology', '/theme-demo/news88/vehicle.webp', false],
                ['car', 'Xu hướng xe điện đô thị trong năm nay', 'Urban electric vehicle trends this year', '/theme-demo/ec907/hero-gear.png', false],
                ['car', 'Những lưu ý trước chuyến đi đường dài', 'What to check before a long road trip', '/theme-demo/news88/travel-mountain.webp', false],
                ['travel', '5 mẹo đi tàu biển để hành trình không mệt', 'Five tips for a comfortable ferry journey', '/theme-demo/news88/travel-cruise.webp', false],
                ['travel', 'Quán cà phê có không gian như phim hoạt hình', 'A café that feels like an animated film', '/theme-demo/ser103/news-minimal.webp', false],
                ['travel', 'Thiên nhiên kỳ thú trên cung đường phía Bắc', 'Spectacular nature along the northern route', '/theme-demo/news88/travel-lake.webp', false],
                ['entertainment', 'Một ngày hậu trường cùng nghệ sĩ trẻ', 'A day backstage with a young artist', '/theme-demo/ser103/service-photo.webp', false],
                ['entertainment', 'Câu chuyện thú vị phía sau một bức ảnh lan tỏa', 'The story behind a photo that went viral', '/theme-demo/ser103/news-garden.webp', false],
                ['entertainment', 'Điểm hẹn văn hóa mới dành cho gia đình', 'A new cultural destination for families', '/theme-demo/ser103/gallery-aodai.webp', false],
                ['sport', 'Nhịp sống thể thao: Những bài tập nhẹ mà hiệu quả', 'Sports life: simple exercises that deliver results', '/theme-demo/ec908/hero-fitness.png', false],
                ['digital', 'Thiết bị đeo thông minh thay đổi thói quen hằng ngày', 'How smart wearables are changing daily habits', '/theme-demo/news88/digital.webp', false],
            ];

            foreach ($posts as $index => [$categoryKey, $viTitle, $enTitle, $image, $highlight]) {
                $media = CmsMedia::query()->create([
                    'title' => $viTitle,
                    'file_path' => '',
                    'file_url' => $image,
                    'mime_type' => Str::endsWith($image, '.png') ? 'image/png' : 'image/webp',
                    'size' => 0,
                    'alt_text' => $viTitle,
                    'folder_path' => 'theme-demo/news88',
                ]);
                $this->record($media);

                $post = CmsPost::query()->create([
                    'category_id' => $categories[$categoryKey]->id,
                    'featured_media_id' => $media->id,
                    'title' => $viTitle,
                    'slug' => Str::slug('news88-'.$viTitle),
                    'status' => 'published',
                    'excerpt' => 'Thông tin được tổng hợp rõ ràng, ngắn gọn và cập nhật để bạn nắm bắt câu chuyện đáng chú ý trong ngày.',
                    'body' => '<p>NEWS88 mang đến góc nhìn dễ hiểu về câu chuyện đang được quan tâm.</p><h2>Thông tin đáng chú ý</h2><p>Nội dung được biên tập từ các nguồn phù hợp và trình bày theo hướng ngắn gọn, hữu ích cho độc giả.</p>',
                    'publish_at' => now()->subHours($index + 1),
                    'is_highlight' => $highlight,
                ]);
                $this->record($post);
                $this->publishTranslation($post, 'cms_post', [
                    'title' => $enTitle,
                    'slug' => Str::slug('news88-en-'.$enTitle),
                    'excerpt' => 'A clear, concise and timely briefing on one of today’s stories worth knowing.',
                    'body' => '<p>NEWS88 brings an accessible perspective to the stories people are talking about.</p><h2>What matters</h2><p>Each report is edited for clarity and practical context.</p>',
                ]);
            }

            $home = route('site.home', [], false);
            $menuItems = [
                ['label' => 'Trang Chủ', 'url' => $home],
                ['label' => 'Sức Khỏe', 'url' => $home.'#suc-khoe'],
                ['label' => 'Thể Thao', 'url' => route('site.blog.index', [], false)],
                ['label' => 'Góc Nhìn', 'url' => $home.'#tin-moi'],
                ['label' => 'Thế Giới', 'url' => $home.'#tin-noi-bat'],
                ['label' => 'Thời Trang', 'url' => route('site.blog.index', [], false)],
                ['label' => 'Số Hóa', 'url' => route('site.blog.index', [], false)],
                ['label' => 'Xe', 'url' => $home.'#xe'],
                ['label' => 'Du Lịch', 'url' => $home.'#du-lich'],
                ['label' => 'Giải Trí', 'url' => $home.'#giai-tri'],
                ['label' => 'Thư Viện', 'url' => route('site.blog.index', [], false)],
            ];
            $menu = CmsMenu::query()->create(['name' => 'NEWS88 Main Menu', 'location' => 'primary-navigation', 'items' => $menuItems]);
            $this->record($menu);
            $enLabels = ['Home', 'Health', 'Sports', 'Opinion', 'World', 'Fashion', 'Digital', 'Motoring', 'Travel', 'Entertainment', 'Library'];
            $localizedMenuItems = collect($menu->items)->values()->map(function (array $item, int $index) use ($enLabels): array {
                $item['label'] = $enLabels[$index] ?? $item['label'];

                return $item;
            })->all();
            $this->publishTranslation($menu, 'cms_menu', $this->menuLocalization->storagePayload($menu->items, ['items' => $localizedMenuItems]));

            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], [
                'title' => 'Liên hệ tòa soạn', 'status' => 'published',
                'excerpt' => 'Gửi tin, hợp tác nội dung hoặc phản hồi tới NEWS88.',
                'body' => '<p>Đội ngũ biên tập NEWS88 luôn sẵn sàng tiếp nhận phản hồi từ độc giả.</p>', 'publish_at' => now(),
            ]);
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $existingBranding = $profile->exists ? $profile->globalBranding() : [];
            $profile->forceFill([
                'site_name' => $profile->site_name ?: 'NEWS88',
                'website_type' => 'news',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge($existingBranding, [
                    'company_name' => data_get($existingBranding, 'company_name', $profile->site_name ?: 'NEWS88'),
                    'company_description' => data_get($existingBranding, 'company_description', 'Cổng thông tin đa lĩnh vực, nhanh chóng, rõ ràng và đáng tin cậy.'),
                    'support_hotline' => data_get($existingBranding, 'support_hotline', '1900 0088'),
                    'support_email' => data_get($existingBranding, 'support_email', 'newsroom@example.vn'),
                    'support_location' => data_get($existingBranding, 'support_location', 'Thành phố Hồ Chí Minh, Việt Nam'),
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->builder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) {
                $this->record($landing);
            }
            if ($landing) {
                $categoryMap = [
                    'news88_health_posts' => 'health', 'news88_car_posts' => 'car',
                    'news88_travel_posts' => 'travel', 'news88_entertainment_posts' => 'entertainment',
                ];
                foreach ($categoryMap as $blockType => $categoryKey) {
                    $block = $landing->blocks()->where('block_type', $blockType)->first();
                    if ($block) {
                        $block->forceFill(['settings' => array_merge((array) $block->settings, ['category_id' => $categories[$categoryKey]->id])])->save();
                    }
                }

                $landing->blocks()->with('landingPage')->get()->each(function (LandingPageBlock $block): void {
                    $translation = $block->data()->where('locale', 'en')->first();
                    if (! $translation || $translation->translation_status === TranslationStatus::Published) {
                        return;
                    }
                    $content = is_array($translation->content)
                        ? $translation->content
                        : (json_decode((string) $translation->content, true) ?: []);
                    $this->landingLocalization->saveBlockDraft($block, 'en', [
                        'schema_version' => $translation->schema_version,
                        'title' => $translation->title,
                        'subtitle' => $translation->subtitle,
                        'description' => $translation->description,
                        'button_label' => $translation->button_label,
                        'content' => $content,
                    ]);
                    $this->landingLocalization->transitionBlock($block, 'en', TranslationStatus::Ready);
                    $this->landingLocalization->transitionBlock($block, 'en', TranslationStatus::Published);
                });

                $pageTranslation = $landing->data()->where('locale', 'en')->first();
                if ($pageTranslation && $pageTranslation->translation_status !== TranslationStatus::Published) {
                    $this->landingLocalization->savePageDraft($landing, 'en', [
                        'slug' => $pageTranslation->slug ?: 'home',
                        'title' => $pageTranslation->title,
                        'excerpt' => $pageTranslation->excerpt,
                        'meta_title' => $pageTranslation->meta_title,
                        'meta_description' => $pageTranslation->meta_description,
                    ]);
                    $this->landingLocalization->transitionPage($landing, 'en', TranslationStatus::Ready);
                    $this->landingLocalization->transitionPage($landing, 'en', TranslationStatus::Published);
                }
            }

            return ['preset' => $this->preset(), 'counts' => ['categories' => count($categories), 'media' => count($posts), 'posts' => count($posts), 'menus' => 1, 'pages' => $page->wasRecentlyCreated ? 1 : 0, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'media' => 0, 'posts' => 0, 'menus' => 0, 'pages' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LocalizedRoute::query()->where('website_key', $this->siteContext->websiteKey())->where('resource_type', 'landing_page')->whereIn('resource_id', array_map('strval', $pageIds))->delete();
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        $websiteKey = $this->siteContext->websiteKey();
        foreach ([[CmsPost::class, 'posts', 'cms_post'], [CmsCategory::class, 'categories', 'cms_category'], [CmsPage::class, 'pages', 'cms_page'], [CmsMenu::class, 'menus', 'cms_menu'], [CmsMedia::class, 'media', 'cms_media']] as [$model, $key, $resourceType]) {
            if ($modelIds = $ids($model)) {
                ContentTranslation::query()->where('website_key', $websiteKey)->where('resource_type', $resourceType)->whereIn('resource_id', $modelIds)->delete();
                LocalizedRoute::query()->where('website_key', $websiteKey)->where('resource_type', $resourceType)->whereIn('resource_id', array_map('strval', $modelIds))->delete();
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function publishTranslation(Model $model, string $resourceType, array $payload): void
    {
        $translation = $this->localizedContent->saveDraftPayload($this->siteContext->websiteKey(), $resourceType, (string) $model->getKey(), 'en', $payload, false, true);
        $translation = $this->localizedContent->transition($translation, TranslationStatus::Ready);
        $this->localizedContent->transition($translation, TranslationStatus::Published);
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
