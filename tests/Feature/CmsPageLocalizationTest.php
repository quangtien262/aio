<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Enums\TranslationStatus;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\LocalizedRoute;
use App\Models\Site;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CmsPageLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $moduleManager = app(ModuleManager::class);
        $moduleManager->install('cms');
        $moduleManager->enable('cms');
        CmsPage::query()->withoutGlobalScopes()->delete();
        CmsPageTranslation::query()->withoutGlobalScopes()->delete();
        LocalizedRoute::query()
            ->withoutGlobalScopes()
            ->where('resource_type', 'cms_page')
            ->delete();
        Site::query()->firstOrCreate(
            ['website_key' => 'website-main'],
            [
                'name' => 'Main Website',
                'domain' => 'localhost',
                'theme_key' => 'SHOP601',
                'status' => 'active',
            ],
        );
    }

    public function test_admin_manages_page_content_and_publish_workflow_per_locale(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');

        $response = $this->postJson('/admin/api/cms/pages', [
            'locale' => 'vi',
            'title' => 'Giới thiệu',
            'slug' => 'gioi-thieu',
            'excerpt' => 'Giới thiệu tiếng Việt',
            'body' => '<p>Nội dung tiếng Việt</p>',
            'meta_title' => 'Giới thiệu công ty',
            'meta_description' => 'Thông tin giới thiệu công ty.',
        ])->assertCreated()
            ->assertJsonPath('data.translations.vi.translation_status', 'draft');

        $pageId = (int) $response->json('data.id');

        $this->assertDatabaseHas('cms_page_translations', [
            'cms_page_id' => $pageId,
            'locale' => 'vi',
            'slug' => 'gioi-thieu',
            'translation_status' => TranslationStatus::Draft->value,
        ]);
        $this->get('/vi/p/gioi-thieu')->assertNotFound();

        $this->postJson("/admin/api/cms/pages/{$pageId}/translations/vi/transition", [
            'translation_status' => 'ready',
        ])->assertOk()
            ->assertJsonPath('data.translation.translation_status', 'ready');
        $this->postJson("/admin/api/cms/pages/{$pageId}/translations/vi/transition", [
            'translation_status' => 'published',
        ])->assertOk()
            ->assertJsonPath('data.translation.translation_status', 'published');

        $this->putJson("/admin/api/cms/pages/{$pageId}", [
            'locale' => 'en',
            'title' => 'About us',
            'slug' => 'about-us',
            'excerpt' => 'English introduction',
            'body' => '<p>English content</p>',
            'meta_title' => 'About our company',
            'meta_description' => 'Learn more about our company.',
        ])->assertOk()
            ->assertJsonPath('data.translations.en.translation_status', 'draft');

        $this->getJson('/admin/api/cms/pages?locale=en')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'About us')
            ->assertJsonPath('data.items.0.slug', 'about-us')
            ->assertJsonPath('data.items.0.status', 'draft')
            ->assertJsonPath('data.active_locale', 'en');

        $this->get('/en/p/about-us')->assertNotFound();

        $this->postJson("/admin/api/cms/pages/{$pageId}/translations/en/transition", [
            'translation_status' => 'ready',
        ])->assertOk();
        $this->postJson("/admin/api/cms/pages/{$pageId}/translations/en/transition", [
            'translation_status' => 'published',
        ])->assertOk();

        $this->get('/vi/p/gioi-thieu')
            ->assertOk()
            ->assertSee('Nội dung tiếng Việt', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('/en/p/about-us', false);

        $this->get('/en/p/about-us')
            ->assertOk()
            ->assertSee('English content', false)
            ->assertSee('/vi/p/gioi-thieu', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/vi/p/gioi-thieu', false)
            ->assertSee('/en/p/about-us', false);
    }

    public function test_chinese_page_slug_is_generated_server_side_and_scoped_by_locale(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');
        $locales = app(WebsiteLocaleManager::class);
        $locales->ensureSystemLocale('zh', 'Chinese', '中文');
        $locales->provisionWebsite('website-main');

        if (! collect(app(\App\Support\Localization\LocaleContext::class)->options('website-main'))->contains('code', 'zh')) {
            $locales->addLocale('website-main', 'zh');
        }

        $first = $this->postJson('/admin/api/cms/pages', [
            'locale' => 'vi',
            'title' => 'Trang thứ nhất',
        ])->assertCreated();
        $second = $this->postJson('/admin/api/cms/pages', [
            'locale' => 'vi',
            'title' => 'Trang thứ hai',
        ])->assertCreated();

        $this->putJson('/admin/api/cms/pages/'.$first->json('data.id'), [
            'locale' => 'zh',
            'title' => '公司简介',
            'slug' => '',
        ])->assertOk()
            ->assertJsonPath('data.translations.zh.slug', 'gong-si-jian-jie');
        $this->putJson('/admin/api/cms/pages/'.$second->json('data.id'), [
            'locale' => 'zh',
            'title' => '公司简介',
            'slug' => '',
        ])->assertOk()
            ->assertJsonPath('data.translations.zh.slug', 'gong-si-jian-jie-2');
    }

    public function test_admin_slug_suggestion_uses_the_same_backend_generator(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');

        $locales = app(WebsiteLocaleManager::class);
        $locales->provisionWebsite('website-main');
        $locales->ensureSystemLocale('zh', 'Chinese', '中文');

        if (! collect(app(\App\Support\Localization\LocaleContext::class)->options('website-main'))->contains('code', 'zh')) {
            $locales->addLocale('website-main', 'zh');
        }

        $this->postJson('/admin/api/localization/slug-suggest', [
            'value' => '联系我们',
            'locale' => 'zh',
            'resource_type' => 'cms_page',
        ])->assertOk()
            ->assertJsonPath('data.slug', 'lian-xi-wo-men')
            ->assertJsonPath('data.locale', 'zh');
    }

    public function test_public_resolver_hides_missing_target_and_redirects_previous_localized_slugs(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giới thiệu',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => 'Nội dung',
        ]);

        $this->get('/en/p/gioi-thieu')
            ->assertNotFound();

        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');
        $this->putJson("/admin/api/cms/pages/{$page->id}", [
            'locale' => 'vi',
            'title' => 'Về chúng tôi',
            'slug' => 've-chung-toi',
            'body' => 'Nội dung mới',
            'translation_status' => 'published',
        ])->assertOk();

        $this->get('/vi/p/gioi-thieu')
            ->assertRedirect('/vi/p/ve-chung-toi');
        $this->get('/vi/p/ve-chung-toi')
            ->assertOk()
            ->assertSee('Nội dung mới', false);

        $this->assertSame(
            1,
            CmsPageTranslation::query()
                ->where('cms_page_id', $page->id)
                ->where('locale', 'vi')
                ->count(),
        );
    }

    public function test_navigation_repair_restores_missing_page_route_and_strict_audit_detects_regression(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giá»›i thiá»‡u',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => 'Ná»™i dung',
        ]);
        LocalizedRoute::query()
            ->where('resource_type', 'cms_page')
            ->where('resource_id', (string) $page->id)
            ->where('locale', 'vi')
            ->delete();

        $this->assertSame(1, Artisan::call('localization:audit', [
            '--website' => 'website-main',
            '--strict' => true,
            '--json' => true,
        ]));
        $this->assertStringContainsString(
            'missing_canonical_route',
            Artisan::output(),
        );

        $this->assertSame(0, Artisan::call(
            'localization:repair-navigation',
            [
                '--website' => 'website-main',
                '--json' => true,
            ],
        ));

        $this->assertDatabaseHas('localized_routes', [
            'website_key' => 'website-main',
            'locale' => 'vi',
            'resource_type' => 'cms_page',
            'resource_id' => (string) $page->id,
            'path' => '/p/gioi-thieu',
            'is_canonical' => true,
            'is_published' => true,
        ]);
        $exitCode = Artisan::call('localization:audit', [
            '--website' => 'website-main',
            '--strict' => true,
            '--json' => true,
        ]);
        $this->assertSame(1, $exitCode);
        $issues = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['issues']);
        $this->assertFalse($issues->contains('type', 'missing_canonical_route'));
    }
}
