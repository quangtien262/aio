<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageLocalizationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_public_resolver_redirects_fallback_and_previous_localized_slugs(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giới thiệu',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => 'Nội dung',
        ]);

        $this->get('/en/p/gioi-thieu')
            ->assertRedirect('/vi/p/gioi-thieu');

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
}
