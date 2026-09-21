<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\LegacyTextEncoding;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Complete, website-scoped demo content with an industry-specific editorial brief. */
class XdCompleteDemoContentProvider implements ThemeDemoContentProvider
{
    public function __construct(private readonly string $key, private readonly array $brief) {}

    public static function definitions(): array
    {
        return json_decode(file_get_contents(resource_path('demo/xd-themes.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function themeKey(): string
    {
        return $this->key;
    }

    public function defaultPreset(): string
    {
        return $this->brief['preset'];
    }

    public function preset(): array
    {
        return ['key' => $this->defaultPreset(), 'label' => $this->brief['brand'], 'description' => 'Bộ mẫu đầy đủ về '.$this->brief['sector'].': dịch vụ, sản phẩm, dự án, tin tức và hình ảnh.'];
    }

    private function create(string $type, array $data): Model
    {
        $model = $type::create($data);
        ThemeDemoRecord::create(['theme_key' => $this->key, 'preset_key' => $this->defaultPreset(), 'model_type' => $type, 'model_id' => $model->id]);

        return $model;
    }

    private function image(int $index): string
    {
        return '/theme-demo/xd-shared/'.$this->brief['image_group'].'-'.(($index % 3) + 1).'.jpg';
    }

    private function slug(string $title): string
    {
        return Str::slug($this->key.'-'.$title);
    }

    private function body(string $title, string $summary): string
    {
        return '<h2>'.e($title).'</h2><p>'.e($summary).'</p><h2>Chuẩn bị và triển khai</h2><p>Trao đổi nhu cầu, khảo sát điều kiện thực tế và thống nhất phạm vi trước khi thực hiện. Các hạng mục, thời gian và chi phí cần được xác nhận bằng văn bản.</p><h2>Kiểm tra và bàn giao</h2><p>Đối chiếu từng hạng mục với yêu cầu ban đầu, ghi nhận kết quả và hướng dẫn các bước tiếp theo. Liên hệ đội ngũ tư vấn để được giải đáp theo trường hợp cụ thể.</p><p><em>Nội dung và hình ảnh minh họa của bộ dữ liệu mẫu, cần được doanh nghiệp duyệt trước khi sử dụng chính thức.</em></p>';
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== $this->defaultPreset()) {
            throw new InvalidArgumentException('Bộ dữ liệu không hợp lệ cho '.$this->key);
        }

        return DB::transaction(function () use ($presetKey): array {
            $purged = $this->delete();
            $brand = $this->brief['brand'];
            $sector = $this->brief['sector'];
            $published = ['status' => 'published', 'publish_at' => now()];
            $media = [];
            for ($i = 0; $i < 3; $i++) {
                $media[] = $this->create(CmsMedia::class, ['title' => $brand.' — ảnh minh họa '.($i + 1), 'alt_text' => 'Minh họa '.$sector, 'file_path' => '', 'file_url' => $this->image($i), 'mime_type' => 'image/jpeg']);
            }
            $serviceCategory = $this->create(CmsServiceCategory::class, ['name' => Str::ucfirst($sector), 'slug' => $this->slug('dich-vu'), 'is_active' => true]);
            foreach ($this->brief['services'] as $i => $title) {
                $summary = $brand.' hỗ trợ '.mb_strtolower($title).' với quy trình khảo sát, đề xuất phương án và kiểm tra kết quả rõ ràng.';
                $service = $this->create(CmsService::class, $published + ['cms_service_category_id' => $serviceCategory->id, 'title' => $title, 'slug' => $this->slug($title), 'summary' => $summary, 'content' => $this->body($title, $summary), 'button_label' => 'Tìm hiểu dịch vụ', 'link_url' => route('site.contact', [], false), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $i]);
                $this->create(CmsServiceImage::class, ['cms_service_id' => $service->id, 'image_url' => $this->image($i), 'alt_text' => $title.' — ảnh minh họa', 'is_featured' => true, 'sort_order' => 0]);
            }
            $category = $this->create(CatalogCategory::class, ['name' => 'Sản phẩm và thiết bị', 'slug' => $this->slug('san-pham'), 'description' => 'Sản phẩm tham khảo cho lĩnh vực '.$sector, 'is_active' => true, 'image_url' => $this->image(1)]);
            foreach ($this->brief['products'] as $i => $title) {
                $summary = $title.' phục vụ nhu cầu '.$sector.'. Hình ảnh minh họa lĩnh vực; liên hệ để xác nhận mẫu, quy cách và giá thực tế.';
                $product = $this->create(CatalogProduct::class, ['catalog_category_id' => $category->id, 'name' => $title, 'slug' => $this->slug($title), 'sku' => $this->key.'-DEMO-'.($i + 1), 'price' => 250000 * ($i + 1), 'stock' => 20, 'short_description' => $summary, 'detail_content' => $this->body($title, $summary), 'image_url' => $this->brief['product_images'][$i] ?? $this->image($i), 'is_active' => true, 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $i]);
                $this->create(CatalogProductImage::class, ['catalog_product_id' => $product->id, 'image_url' => $product->image_url, 'alt_text' => 'Ảnh minh họa '.$sector, 'sort_order' => 0]);
            }
            $news = $this->create(CmsCategory::class, ['name' => 'Kinh nghiệm và kiến thức', 'slug' => $this->slug('tin-tuc'), 'description' => 'Góc chia sẻ về '.$sector]);
            $postTitles = ['Những điều cần chuẩn bị trước khi sử dụng dịch vụ '.$sector, 'Cách đánh giá một phương án '.$sector.' phù hợp', 'Checklist bàn giao và theo dõi chất lượng'];
            foreach ($postTitles as $i => $title) {
                $summary = 'Gợi ý từ '.$brand.' giúp bạn xác định nhu cầu, so sánh phạm vi công việc và trao đổi rõ yêu cầu về '.$sector.'.';
                $this->create(CmsPost::class, $published + ['category_id' => $news->id, 'title' => $title, 'slug' => $this->slug($title), 'excerpt' => $summary, 'body' => $this->body($title, $summary), 'featured_media_id' => $media[$i]->id, 'meta_title' => $title, 'meta_description' => $summary, 'is_highlight' => true]);
            }
            foreach (array_slice($this->brief['services'], 0, 3) as $i => $serviceTitle) {
                $title = $serviceTitle.' — phương án minh họa';
                $summary = 'Hồ sơ mẫu mô tả cách '.$brand.' tiếp nhận yêu cầu, tổ chức thực hiện và bàn giao hạng mục '.mb_strtolower($serviceTitle).'.';
                $project = $this->create(CmsProject::class, $published + ['title' => $title, 'slug' => $this->slug($title), 'summary' => $summary, 'content' => $this->body($title, $summary), 'is_featured' => true, 'sort_order' => $i]);
                $this->create(CmsProjectImage::class, ['cms_project_id' => $project->id, 'image_url' => $this->image($i), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
            }
            $about = null;
            foreach (['Giới thiệu', 'Quy trình hợp tác', 'Chính sách dịch vụ'] as $i => $title) {
                $summary = $brand.' đồng hành cùng khách hàng trong lĩnh vực '.$sector.', từ xác định nhu cầu đến triển khai và hỗ trợ sau bàn giao.';
                $page = $this->create(CmsPage::class, $published + ['title' => $title.' '.$brand, 'slug' => $this->slug($title), 'excerpt' => $summary, 'body' => $this->body($title.' '.$brand, $summary), 'featured_media_id' => $media[$i]->id, 'meta_title' => $title.' | '.$brand]);
                $about ??= $page;
            }
            foreach (['Tư vấn khách hàng', 'Điều phối triển khai', 'Kiểm soát chất lượng'] as $i => $role) {
                $name = ['Nguyễn Minh Anh', 'Trần Hoàng Nam', 'Lê Thu Hà'][$i];
                $member = $this->create(CmsTeamMember::class, $published + ['name' => $name, 'slug' => $this->slug($name), 'role' => $role, 'summary' => 'Nhân sự minh họa phụ trách '.mb_strtolower($role).' tại '.$brand, 'bio' => '<p>Đồng hành trong quá trình tiếp nhận yêu cầu, triển khai và bàn giao.</p>', 'is_featured' => true, 'sort_order' => $i]);
                $this->create(CmsTeamMemberImage::class, ['cms_team_member_id' => $member->id, 'image_url' => '/theme-demo/xd-shared/person-'.($i + 1).'.jpg', 'alt_text' => 'Chân dung minh họa', 'is_featured' => true, 'sort_order' => 0]);
                $this->create(CmsTestimonial::class, $published + ['name' => ['Anh Hải', 'Chị Mai', 'Anh Dũng'][$i], 'role' => 'Đánh giá minh họa', 'quote' => ['Phạm vi công việc và các bước thực hiện được trao đổi rõ ràng.', 'Đội ngũ chủ động cập nhật tiến độ và hướng dẫn khi bàn giao.', 'Thông tin tư vấn giúp chúng tôi lựa chọn phương án phù hợp nhu cầu.'][$i], 'image_url' => '/theme-demo/xd-shared/person-'.($i + 1).'.jpg', 'image_alt' => 'Chân dung minh họa', 'is_featured' => true, 'sort_order' => $i]);
            }
            for ($i = 0; $i < 6; $i++) {
                $this->create(CmsPartner::class, $published + ['title' => 'Đối tác mẫu '.($i + 1), 'slug' => $this->slug('doi-tac-'.($i + 1)), 'description' => 'Đối tác minh họa, thay bằng thông tin được xác nhận khi vận hành.', 'image_url' => '/theme-demo/xd-shared/partner-'.($i + 1).'.svg', 'image_alt' => 'Đối tác mẫu '.($i + 1), 'link_url' => route('site.contact', [], false), 'is_featured' => true, 'sort_order' => $i]);
            }
            for ($i = 0; $i < 2; $i++) {
                $title = $i === 0 ? 'Giải pháp '.$sector.' phù hợp nhu cầu' : 'Đồng hành từ tư vấn đến bàn giao';
                $summary = $brand.' kết nối chuyên môn, quy trình và sự tận tâm trong từng hạng mục.';
                $this->create(SiteBanner::class, ['theme_key' => $this->key, 'placement' => strtolower($this->key).'-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $this->image($i), 'link_url' => route('site.contact', [], false), 'badge' => $brand, 'metadata' => ['kicker' => $brand, 'eyebrow' => $brand, 'summary' => $summary, 'button_label' => 'Nhận tư vấn'], 'sort_order' => $i, 'is_active' => true]);
            }
            $this->create(CmsMenu::class, ['name' => $this->key.' Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'link_type' => 'home', 'url' => route('site.home', [], false)],
                ['label' => 'Giới thiệu', 'link_type' => 'page', 'link_value' => (string) $about->id, 'url' => route('site.pages.show', ['slug' => $about->slug], false)],
                ['label' => 'Dịch vụ', 'link_type' => 'service-category', 'link_value' => (string) $serviceCategory->id, 'url' => route('site.services.category', ['slug' => $serviceCategory->slug], false)],
                ['label' => 'Sản phẩm', 'link_type' => 'catalog-index', 'url' => route('site.catalog.search', [], false)],
                ['label' => 'Dự án', 'link_type' => 'project-index', 'url' => route('site.projects.index', [], false)],
                ['label' => 'Tin tức', 'link_type' => 'post-category', 'link_value' => (string) $news->id, 'url' => route('site.blog.category', ['slug' => $news->slug], false)],
                ['label' => 'Liên hệ', 'link_type' => 'contact', 'url' => route('site.contact', [], false)],
            ]]);
            $profile = SiteProfile::firstOrNew();
            if (blank(data_get($profile->branding, 'logo_url')) || str_starts_with((string) data_get($profile->branding, 'logo_url'), '/theme-demo/xd-shared/logo-')) {
                $profile->branding = array_merge((array) $profile->branding, ['logo_url' => '/theme-demo/xd-shared/logo-'.strtolower($this->key).'.svg']);
            }
            $profile->forceFill(['site_name' => $brand, 'active_theme_key' => $this->key, 'website_type' => 'service', 'branding' => array_merge((array) $profile->branding, ['company_name' => $brand, 'company_description' => 'Giải pháp '.$sector.' với quy trình minh bạch và hỗ trợ tận tâm.', 'support_email' => 'contact@example.com', 'support_location' => 'Hà Nội và TP.HCM', 'demo_preset_key' => $presetKey])])->save();
            $builder = app(LandingPageBuilder::class);
            $existing = LandingPage::where('theme_key', $this->key)->where('is_home', true)->first();
            $page = $builder->resolveHome(app(SiteContext::class)->websiteKey(), $this->key, true);
            if ($page && ! $existing) {
                ThemeDemoRecord::create(['theme_key' => $this->key, 'preset_key' => $presetKey, 'model_type' => LandingPage::class, 'model_id' => $page->id]);
                $this->prepareBlocks($page);
            }

            return ['preset' => $this->preset(), 'purged' => $purged, 'counts' => ['services' => 4, 'products' => 3, 'projects' => 3, 'posts' => 3, 'pages' => 3, 'media' => 3, 'menus' => 1, 'banners' => 2, 'team_members' => 3, 'testimonials' => 3, 'partners' => 6, 'landing_pages' => $page && ! $existing ? 1 : 0]];
        });
    }

    private function prepareBlocks(LandingPage $page): void
    {
        foreach ($page->blocks()->with('data')->get() as $index => $block) {
            $imageIndex = $index;
            $replace = function ($value, $key = '') use (&$replace, &$imageIndex) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $value[$k] = $replace($v, (string) $k);
                    }
                } elseif (is_string($value)) {
                    if (preg_match('/image|photo|avatar|background/', $key) && str_starts_with($value, 'http')) {
                        return $this->image($imageIndex++);
                    }
                    $value = app(LegacyTextEncoding::class)->repair($value);
                    if (str_starts_with($value, '#')) {
                        return route('site.home', [], false).$value;
                    }
                }

                return $value;
            };
            $settings = $replace((array) $block->settings);
            if ($block->block_type === 'hero_slider') {
                $settings['source'] = 'site_banners';
                $settings['placement'] = strtolower($this->key).'-hero-slider';
                $settings['limit'] = 2;
            }
            $block->update(['media' => $replace((array) $block->media), 'settings' => $settings]);
            foreach ($block->data as $data) {
                $content = json_decode($data->content ?? '{}', true);
                if ($block->block_type === 'hero_slider' && $data->locale === 'vi') {
                    $content['slides'] = SiteBanner::where('theme_key', $this->key)->orderBy('sort_order')->get()->map(fn ($banner) => [
                        'title' => $banner->title, 'summary' => $banner->subtitle, 'kicker' => $this->brief['brand'],
                        'image' => $banner->image_url, 'button_label' => 'Nhận tư vấn', 'link_url' => $banner->link_url, 'url' => $banner->link_url,
                    ])->all();
                }
                $updates = ['content' => json_encode($replace($content ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                if ($data->locale === 'vi') {
                    $updates['title'] = match (true) {
                        str_contains($block->block_type, 'about') => 'Về '.$this->brief['brand'],
                        str_contains($block->block_type, 'service') => 'Dịch vụ '.$this->brief['sector'],
                        str_contains($block->block_type, 'team') => 'Đội ngũ đồng hành',
                        str_contains($block->block_type, 'post') => 'Kiến thức và kinh nghiệm',
                        str_contains($block->block_type, 'project') => 'Dự án và phương án tham khảo',
                        str_contains($block->block_type, 'partner') => 'Kết nối cùng phát triển',
                        str_contains($block->block_type, 'testimonial') => 'Chia sẻ từ khách hàng',
                        str_contains($block->block_type, 'contact') => 'Trao đổi nhu cầu của bạn',
                        default => app(LegacyTextEncoding::class)->repair($data->title ?? ''),
                    };
                    $updates['subtitle'] = $this->brief['brand'];
                    $updates['description'] = 'Giải pháp '.$this->brief['sector'].' được xây dựng từ nhu cầu thực tế, phạm vi rõ ràng và sự phối hợp trong từng bước triển khai.';
                }
                $data->update($updates);
            }
        }
    }

    public function delete(): array
    {
        // Includes older XD presets, but never unmarked records or another website's records.
        $records = ThemeDemoRecord::where('theme_key', $this->key)->get();
        $counts = [];
        foreach ([LandingPage::class, CatalogProduct::class, CmsPost::class, CmsProject::class, CmsService::class, CmsTeamMember::class, CmsPage::class, CmsMenu::class, SiteBanner::class, CmsTestimonial::class, CmsPartner::class, CatalogCategory::class, CmsCategory::class, CmsServiceCategory::class, CmsMedia::class] as $type) {
            $ids = $records->where('model_type', $type)->pluck('model_id');
            if ($type === LandingPage::class) {
                $blocks = LandingPageBlock::whereIn('landing_page_id', $ids)->pluck('id');
                LandingPageBlockData::whereIn('landing_page_block_id', $blocks)->delete();
                LandingPageBlock::whereKey($blocks)->delete();
                LandingPageData::whereIn('landing_page_id', $ids)->delete();
            }
            foreach ([CatalogProduct::class => [CatalogProductImage::class, 'catalog_product_id'], CmsService::class => [CmsServiceImage::class, 'cms_service_id'], CmsProject::class => [CmsProjectImage::class, 'cms_project_id'], CmsTeamMember::class => [CmsTeamMemberImage::class, 'cms_team_member_id']] as $parent => [$child, $fk]) {
                if ($type === $parent) {
                    $child::whereIn($fk, $ids)->delete();
                }
            }
            $counts[(new $type)->getTable()] = $type::whereKey($ids)->delete();
        }
        ThemeDemoRecord::whereKey($records->pluck('id'))->delete();

        return $counts;
    }
}
