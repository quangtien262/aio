<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsService;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LocalizedContentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDemoWebsiteFinalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_book920_demo_has_real_navigation_about_page_and_cms_testimonials(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->defaultPresetForTheme('BOOK920');

        $this->assertNotNull($preset);
        $result = $generator->generate('BOOK920', $preset);

        $menu = CmsMenu::query()
            ->where('location', 'primary-navigation')
            ->latest('updated_at')
            ->latest('id')
            ->firstOrFail();
        $items = collect($menu->items);

        $this->assertSame(
            ['Trang chủ', 'Giới thiệu', 'Sản phẩm', 'Tin tức', 'Liên hệ'],
            $items->pluck('label')->all(),
        );
        $this->assertSame(
            ['/', '/p/gioi-thieu', '/tim-kiem', '/c', '/contact'],
            $items->pluck('url')->all(),
        );
        $this->assertFalse($items->contains(
            fn (array $item): bool => str_starts_with((string) ($item['url'] ?? ''), '#'),
        ));
        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'website_key' => 'website-main',
        ]);

        $landing = LandingPage::query()
            ->where('theme_key', 'BOOK920')
            ->where('is_home', true)
            ->firstOrFail();
        $block = $landing->blocks()->where('block_type', 'book920_testimonials')->firstOrFail();

        $this->assertSame('cms_testimonials', data_get($block->settings, 'source'));
        $this->assertSame(3, CmsTestimonial::query()->count());
        $this->assertSame(3, data_get($result, 'counts.testimonials'));
        $dynamicItems = collect(
            app(LandingPageBuilder::class)
                ->serializeBlock($block->load('data'), 'vi', 'vi', true)['dynamic_items'],
        );
        $this->assertCount(3, $dynamicItems);
        $this->assertTrue($dynamicItems->every(
            fn (array $item): bool => filled($item['name'] ?? null)
                && ($item['name'] ?? null) === ($item['title'] ?? null)
                && filled($item['quote'] ?? null)
                && ($item['quote'] ?? null) === ($item['summary'] ?? null),
        ));
    }

    public function test_ec914_demo_moves_testimonials_and_partners_from_blocks_into_cms(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->defaultPresetForTheme('EC914');

        $this->assertNotNull($preset);
        $result = $generator->generate('EC914', $preset);
        $landing = LandingPage::query()
            ->where('theme_key', 'EC914')
            ->where('is_home', true)
            ->firstOrFail();
        $testimonialBlock = $landing->blocks()
            ->where('block_type', 'ec914_testimonials')
            ->firstOrFail();
        $partnerBlock = $landing->blocks()
            ->where('block_type', 'ec914_partners')
            ->firstOrFail();

        $this->assertSame('cms_testimonials', data_get($testimonialBlock->settings, 'source'));
        $this->assertSame('cms_partners', data_get($partnerBlock->settings, 'source'));
        $this->assertGreaterThanOrEqual(1, CmsTestimonial::query()->count());
        $this->assertGreaterThanOrEqual(1, CmsPartner::query()->count());
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'counts.testimonials'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'counts.partners'));

        $builder = app(LandingPageBuilder::class);
        $this->assertNotEmpty(
            $builder->serializeBlock($testimonialBlock->load('data'), 'vi', 'vi', true)['dynamic_items'],
        );
        $this->assertNotEmpty(
            $builder->serializeBlock($partnerBlock->load('data'), 'vi', 'vi', true)['dynamic_items'],
        );
    }

    public function test_bds701_demo_uses_real_estate_routes_and_preserves_user_content(): void
    {
        $userAbout = CmsPage::query()->create([
            'title' => 'Giới thiệu do người dùng tạo',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'excerpt' => 'Không được ghi đè.',
            'body' => '<p>Nội dung thật.</p>',
            'publish_at' => now(),
        ]);
        $userMenu = CmsMenu::query()->create([
            'name' => 'Menu do người dùng tạo',
            'location' => 'primary-navigation',
            'items' => [['label' => 'Menu riêng', 'url' => '/menu-rieng']],
        ]);
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->defaultPresetForTheme('BDS701');

        $this->assertNotNull($preset);
        $generator->generate('BDS701', $preset);

        $demoMenuId = ThemeDemoRecord::query()
            ->where('theme_key', 'BDS701')
            ->where('model_type', CmsMenu::class)
            ->latest('id')
            ->value('model_id');
        $demoItems = collect(CmsMenu::query()->findOrFail($demoMenuId)->items);

        $this->assertSame(
            ['Trang chủ', 'Tin rao', 'Tin tức', 'Giới thiệu', 'Liên hệ'],
            $demoItems->pluck('label')->all(),
        );
        $this->assertContains('/bds', $demoItems->pluck('url')->all());
        $this->assertSame('/p/gioi-thieu', $demoItems->firstWhere('label', 'Giới thiệu')['url']);
        $this->assertSame('Giới thiệu do người dùng tạo', $userAbout->fresh()->title);
        $this->assertSame(
            [['label' => 'Menu riêng', 'url' => '/menu-rieng']],
            $userMenu->fresh()->items,
        );
        $this->assertDatabaseMissing('theme_demo_records', [
            'theme_key' => 'BDS701',
            'model_type' => CmsPage::class,
            'model_id' => $userAbout->id,
        ]);
    }

    public function test_deleting_demo_removes_supplemental_cms_rows(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->defaultPresetForTheme('BOOK920');
        $generator->generate('BOOK920', $preset);

        $testimonialIds = CmsTestimonial::query()->pluck('id')->all();
        $aboutId = CmsPage::query()->where('slug', 'gioi-thieu')->value('id');

        $this->assertNotEmpty($testimonialIds);
        $this->assertNotNull($aboutId);

        $generator->delete('BOOK920');

        $this->assertSame(0, CmsTestimonial::query()->whereKey($testimonialIds)->count());
        $this->assertNull(CmsPage::query()->find($aboutId));
        $this->assertFalse(ThemeDemoRecord::query()
            ->where('theme_key', 'BOOK920')
            ->exists());
    }

    public function test_theme_headers_with_previous_static_navigation_consume_the_cms_menu(): void
    {
        $themeKeys = [
            'BDS701',
            'BOOK920',
            'CA0050',
            'EC903',
            'EC904',
            'EC905',
            'EC906',
            'EC907',
            'EC908',
            'EC909',
            'EC911',
            'EC912',
            'EC913',
            'EC914',
            'EC915',
            'EC916',
            'EC917',
            'SHOP605',
            'SPA111',
            'XD0325',
        ];

        foreach ($themeKeys as $themeKey) {
            $header = file_get_contents(
                base_path("themes/{$themeKey}/views/partials/header.blade.php"),
            );

            $this->assertIsString($header, "Không đọc được header của {$themeKey}.");
            $this->assertStringContainsString(
                'top_menu',
                $header,
                "Header {$themeKey} chưa lấy menu chính từ CMS.",
            );
            $this->assertStringNotContainsString(
                '$primaryMenu',
                $header,
                "Header {$themeKey} vẫn đang dùng biến menu riêng cũ.",
            );
        }
    }

    public function test_current_theme_menu_does_not_inherit_labels_from_an_older_theme_menu(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Bookle',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'BOOK920',
            'branding' => [],
        ]);
        $oldMenu = CmsMenu::query()->create([
            'name' => 'Menu theme cũ',
            'location' => 'primary-navigation',
            'items' => [
                ['label' => 'Tất cả tin rao', 'url' => '/bds'],
                ['label' => 'Tin tức cũ', 'url' => '/c'],
            ],
        ]);
        app(LocalizedContentRepository::class)->savePublishedFieldByKey(
            'website-main',
            'vi',
            "cms_menu.{$oldMenu->id}.items.0.label",
            'Tất cả tin rao',
        );
        $foreignService = CmsService::query()->create([
            'title' => 'Dịch vụ mẫu của theme khác',
            'slug' => 'dich-vu-theme-khac',
            'status' => 'published',
            'summary' => 'Không được đưa vào menu BOOK920.',
            'publish_at' => now(),
        ]);
        ThemeDemoRecord::query()->create([
            'theme_key' => 'DN202',
            'preset_key' => 'dn202-demo',
            'model_type' => CmsService::class,
            'model_id' => $foreignService->id,
        ]);

        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('BOOK920', $generator->defaultPresetForTheme('BOOK920'));

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();

        $response
            ->assertSee('>Trang chủ</a>', false)
            ->assertSee('>Giới thiệu</a>', false)
            ->assertSee('>Sản phẩm</a>', false)
            ->assertSee('>Tin tức</a>', false)
            ->assertSee('>Liên hệ</a>', false)
            ->assertDontSee('>Dịch vụ</a>', false)
            ->assertDontSee('>Tất cả tin rao</a>', false);
    }

    public function test_dynamic_testimonials_exclude_demo_rows_owned_by_another_theme(): void
    {
        $foreign = CmsTestimonial::query()->create([
            'name' => 'Khách hàng theme khác',
            'role' => 'Không thuộc BOOK920',
            'quote' => 'Nội dung này không được rò sang theme hiện tại.',
            'status' => 'published',
            'publish_at' => now(),
            'is_featured' => true,
            'sort_order' => 0,
        ]);
        ThemeDemoRecord::query()->create([
            'theme_key' => 'DN202',
            'preset_key' => 'dn202-demo',
            'model_type' => CmsTestimonial::class,
            'model_id' => $foreign->id,
        ]);
        CmsTestimonial::query()->create([
            'name' => 'Nội dung thật có sẵn',
            'role' => 'Người dùng tạo',
            'quote' => 'Được giữ lại nhưng xếp sau dữ liệu mẫu của theme hiện tại.',
            'status' => 'published',
            'publish_at' => now(),
            'is_featured' => true,
            'sort_order' => 0,
        ]);

        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('BOOK920', $generator->defaultPresetForTheme('BOOK920'));
        $block = LandingPage::query()
            ->where('theme_key', 'BOOK920')
            ->where('is_home', true)
            ->firstOrFail()
            ->blocks()
            ->where('block_type', 'book920_testimonials')
            ->firstOrFail();
        $items = collect(
            app(LandingPageBuilder::class)
                ->serializeBlock($block->load('data'), 'vi', 'vi', true)['dynamic_items'],
        );

        $this->assertCount(3, $items);
        $this->assertFalse($items->contains(
            fn (array $item): bool => ($item['name'] ?? null) === 'Khách hàng theme khác',
        ));
        $this->assertFalse($items->contains(
            fn (array $item): bool => ($item['name'] ?? null) === 'Nội dung thật có sẵn',
        ));
    }
}
