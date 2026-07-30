<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\Admin;
use App\Models\CmsPost;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LandingPageLocalization;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedContentAndLandingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_content_list_uses_the_requested_editable_locale(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');
        $post = CmsPost::query()->create([
            'website_key' => 'website-main',
            'title' => 'Tin tiếng Việt',
            'slug' => 'tin-tieng-viet',
            'status' => 'published',
            'body' => '<p>Nội dung nguồn</p>',
            'publish_at' => now(),
        ]);
        app(\App\Support\Localization\LocalizedContentRepository::class)
            ->saveDraftPayload(
                'website-main',
                'cms_post',
                (string) $post->id,
                'en',
                [
                    'title' => 'English news',
                    'slug' => 'english-news',
                    'body' => '<p>English draft</p>',
                ],
            );

        $this->getJson('/admin/api/cms/posts?locale=en')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'English news')
            ->assertJsonPath('data.items.0.slug', 'english-news')
            ->assertJsonPath('data.items.0.status', 'draft')
            ->assertJsonPath('data.items.0._content_locale', 'en')
            ->assertJsonPath('data.items.0._translation_status', 'draft');

        $this->getJson('/admin/api/cms/posts?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Tin tiếng Việt')
            ->assertJsonPath('data.items.0.slug', 'tin-tieng-viet')
            ->assertJsonPath('data.items.0.status', 'published');
    }

    public function test_generic_content_uses_localized_slug_workflow_and_keeps_old_slug_redirect(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');
        $post = CmsPost::query()->create([
            'website_key' => 'website-main',
            'title' => 'Tin nguồn',
            'slug' => 'tin-nguon',
            'status' => 'published',
            'excerpt' => 'Mô tả nguồn',
            'body' => '<p>Nội dung nguồn</p>',
            'publish_at' => now(),
        ]);

        $this->assertDatabaseHas('content_translations', [
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'locale' => 'vi',
            'slug' => 'tin-nguon',
            'translation_status' => TranslationStatus::Published->value,
        ]);

        $this->getJson("/admin/api/localization/content/cms_post/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.fields', [
                'title',
                'slug',
                'excerpt',
                'body',
                'meta_title',
                'meta_description',
                'meta_keywords',
            ])
            ->assertJsonPath('data.source_locale', 'vi');

        $this->putJson("/admin/api/localization/content/cms_post/{$post->id}/en", [
            'payload' => [
                'title' => 'Source news',
                'slug' => 'source-news',
                'excerpt' => 'English summary',
                'body' => '<p>English body</p>',
            ],
            'publish' => true,
        ])->assertOk()
            ->assertJsonPath('data.translation_status', 'published');

        $this->get('/en/n/source-news')
            ->assertOk()
            ->assertSee('English body', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('/vi/n/tin-nguon', false);

        $this->putJson("/admin/api/localization/content/cms_post/{$post->id}/en", [
            'payload' => [
                'title' => 'Updated news',
                'slug' => 'updated-news',
                'excerpt' => 'Updated summary',
                'body' => '<p>Updated body</p>',
            ],
            'publish' => true,
        ])->assertOk();

        $this->get('/en/n/source-news')
            ->assertRedirect('/en/n/updated-news');
        $this->get('/en/n/updated-news')
            ->assertOk()
            ->assertSee('Updated body', false);

        $this->assertSame(2, ContentTranslation::query()
            ->where('resource_type', 'cms_post')
            ->where('resource_id', (string) $post->id)
            ->count());
    }

    public function test_source_changes_mark_completed_translations_outdated_and_unpublish_their_routes(): void
    {
        $post = CmsPost::query()->create([
            'website_key' => 'website-main',
            'title' => 'Tin nguồn',
            'slug' => 'tin-nguon',
            'status' => 'published',
            'body' => '<p>Nội dung nguồn</p>',
            'publish_at' => now(),
        ]);
        $translation = app(\App\Support\Localization\LocalizedContentRepository::class)
            ->saveDraftPayload(
                'website-main',
                'cms_post',
                (string) $post->id,
                'en',
                [
                    'title' => 'Source news',
                    'slug' => 'source-news',
                    'body' => '<p>English content</p>',
                ],
            );
        $repository = app(\App\Support\Localization\LocalizedContentRepository::class);
        $repository->transition($translation, TranslationStatus::Ready);
        $repository->transition($translation->fresh(), TranslationStatus::Published);

        $post->update(['title' => 'Tin nguồn đã đổi']);

        $this->assertDatabaseHas('content_translations', [
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'locale' => 'en',
            'translation_status' => TranslationStatus::Outdated->value,
        ]);
        $this->assertDatabaseHas('localized_routes', [
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'locale' => 'en',
            'is_published' => false,
        ]);
    }

    public function test_localized_content_api_never_exposes_a_resource_from_another_website(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');
        $post = CmsPost::query()->withoutGlobalScopes()->create([
            'website_key' => 'website-secondary',
            'title' => 'Private website post',
            'slug' => 'private-website-post',
            'status' => 'published',
            'body' => '<p>Private</p>',
            'publish_at' => now(),
        ]);

        $this->getJson("/admin/api/localization/content/cms_post/{$post->id}")
            ->assertNotFound();
        $this->putJson("/admin/api/localization/content/cms_post/{$post->id}/en", [
            'payload' => ['title' => 'Leaked', 'slug' => 'leaked'],
            'publish' => true,
        ])->assertNotFound();
    }

    public function test_landing_page_publish_guard_requires_every_visible_block_in_target_locale(): void
    {
        app(WebsiteLocaleManager::class)->updateLocale(
            'website-main',
            'en',
            ['is_published' => true],
        );
        $page = LandingPage::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'SHOP601',
            'page_type' => 'landing',
            'slug' => 'chien-dich',
            'status' => 'draft',
            'template' => 'landing',
            'is_home' => false,
            'sort_order' => 10,
        ]);
        $localization = app(LandingPageLocalization::class);
        $source = $localization->savePageDraft($page, 'vi', [
            'slug' => 'chien-dich',
            'title' => 'Chiến dịch',
        ]);
        $localization->transitionPage($page, 'vi', TranslationStatus::Ready);
        $localization->transitionPage($page, 'vi', TranslationStatus::Published);
        $block = LandingPageBlock::query()->create([
            'landing_page_id' => $page->id,
            'theme_key' => 'SHOP601',
            'block_type' => 'hero',
            'schema_version' => 1,
            'sort_order' => 10,
            'is_visible' => true,
        ]);
        $sourceBlock = $localization->saveBlockDraft($block, 'vi', [
            'title' => 'Khối nguồn',
            'content' => ['items' => [['title' => 'Mục nguồn']]],
        ]);
        $localization->transitionBlock($block, 'vi', TranslationStatus::Ready);
        $localization->transitionBlock($block, 'vi', TranslationStatus::Published);
        $target = $localization->savePageDraft($page, 'en', [
            'slug' => 'campaign',
            'title' => 'Campaign',
        ]);
        $localization->transitionPage($page, 'en', TranslationStatus::Ready);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $localization->transitionPage($page, 'en', TranslationStatus::Published);
    }

    public function test_landing_page_becomes_public_after_target_blocks_are_published(): void
    {
        app(WebsiteLocaleManager::class)->updateLocale(
            'website-main',
            'en',
            ['is_published' => true],
        );
        $page = LandingPage::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'SHOP601',
            'page_type' => 'landing',
            'slug' => 'chien-dich',
            'status' => 'draft',
            'template' => 'landing',
            'is_home' => false,
            'sort_order' => 10,
        ]);
        $localization = app(LandingPageLocalization::class);
        $localization->savePageDraft($page, 'vi', [
            'slug' => 'chien-dich',
            'title' => 'Chiến dịch',
        ]);
        $localization->transitionPage($page, 'vi', TranslationStatus::Ready);
        $localization->transitionPage($page, 'vi', TranslationStatus::Published);
        $block = LandingPageBlock::query()->create([
            'landing_page_id' => $page->id,
            'theme_key' => 'SHOP601',
            'block_type' => 'hero',
            'schema_version' => 1,
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        foreach ([
            'vi' => ['title' => 'Khối nguồn', 'content' => ['items' => [['title' => 'Nguồn']]]],
            'en' => ['title' => 'Hero block', 'content' => ['items' => [['title' => 'English']]]],
        ] as $locale => $payload) {
            $localization->saveBlockDraft($block, $locale, $payload);
            $localization->transitionBlock($block, $locale, TranslationStatus::Ready);
            $localization->transitionBlock($block, $locale, TranslationStatus::Published);
        }

        $localization->savePageDraft($page, 'en', [
            'slug' => 'campaign',
            'title' => 'Campaign',
        ]);
        $localization->transitionPage($page, 'en', TranslationStatus::Ready);
        $localization->transitionPage($page, 'en', TranslationStatus::Published);

        $resolution = $localization->resolvePublic(
            'website-main',
            'SHOP601',
            'en',
            'campaign',
        );

        $this->assertNotNull($resolution);
        $this->assertSame('en', $resolution['resolved_locale']);
        $this->assertSame('campaign', $resolution['translation']->slug);
        $this->assertTrue($localization->completeness($page, 'en')['complete']);
        $this->assertSame(
            TranslationStatus::Published,
            LandingPageBlockData::query()
                ->where('landing_page_block_id', $block->id)
                ->where('locale', 'en')
                ->firstOrFail()
                ->translation_status,
        );

        $available = app(LandingPageBuilder::class)->availableBlocks('SHOP601');
        $this->assertNotEmpty($available);
        $this->assertSame(1, $available[0]['schema_version']);
    }
}
