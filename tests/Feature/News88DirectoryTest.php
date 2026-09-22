<?php

namespace Tests\Feature;

use App\Models\{Admin, CmsCategory, CmsTopic, SiteProfile};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class News88DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directories_are_scoped_paginated_and_link_to_article_lists(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        foreach ([CmsTopic::class => '/vi/topics', CmsCategory::class => '/vi/news-categories'] as $model => $url) {
            for ($i = 1; $i <= 31; $i++) {
                $model::create(['name' => sprintf('Entry %02d', $i), 'slug' => 'entry-'.$i]);
            }
            $model::create(['website_key' => 'other-site', 'name' => 'Foreign sentinel', 'slug' => 'foreign']);
            if ($model === CmsTopic::class) {
                CmsTopic::create(['name' => 'Inactive sentinel', 'slug' => 'inactive', 'is_active' => false]);
            }
            $this->get($url)->assertOk()->assertSee('Entry 01')->assertSee('Entry 30')->assertDontSee('Entry 31')
                ->assertDontSee('Foreign sentinel')->assertDontSee('Inactive sentinel')
                ->assertSee($model === CmsTopic::class ? '/vi/topics/entry-1' : '/vi/c/entry-1', false);
            $this->get($url.'?page=2')->assertOk()->assertSee('Entry 31')->assertDontSee('Entry 01');
        }
    }

    public function test_topic_directory_uses_published_translation_and_localized_slug(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');
        app(\App\Support\Localization\WebsiteLocaleManager::class)->updateLocale('website-main', 'en', ['is_published' => true]);
        $topic = CmsTopic::create(['name' => 'Original topic', 'slug' => 'original-topic', 'is_active' => true]);
        CmsTopic::create(['name' => 'Untranslated sentinel', 'slug' => 'untranslated', 'is_active' => true]);
        $endpoint = '/admin/api/localization/content/cms_topic/'.$topic->id.'/en';
        $this->putJson($endpoint, ['payload' => ['name' => 'Translated topic', 'slug' => 'translated-topic'], 'publish' => true])->assertOk();
        $this->get('/en/topics')->assertOk()->assertSee('Translated topic')->assertSee('/en/topics/translated-topic', false)->assertDontSee('Untranslated sentinel');
    }

    public function test_menu_accepts_both_directory_types_and_renders_localized_links(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        SiteProfile::firstOrFail()->update(['branding' => ['cms' => ['menu_locations' => [['label' => 'Menu', 'value' => 'primary-navigation']]]]]);
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');
        $this->postJson('/admin/api/cms/menus', ['name' => 'Directory menu', 'location' => 'primary-navigation', 'items' => [
            ['label' => 'All topics', 'link_type' => 'post-topic-index', 'url' => '/topics'],
            ['label' => 'All categories', 'link_type' => 'post-category-index', 'url' => '/news-categories'],
        ]])->assertCreated();
        $this->get('/vi/topics')->assertOk()->assertSee('/vi/topics', false)->assertSee('/vi/news-categories', false)->assertSee('Nội dung đang được cập nhật.');
    }
}
