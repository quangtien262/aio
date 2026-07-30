<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\ContentTranslation;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Support\BusinessContentTranslationService;
use App\Support\FrontendLocalization;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_cms_business_content_translation_entries(): void
    {
        $this->bootstrapThemeDemoStorefront();

        $page = CmsPage::query()->where('slug', 'demo-th0001-gioi-thieu')->firstOrFail();
        $post = CmsPost::query()->where('status', 'published')->whereNotNull('category_id')->firstOrFail();
        $category = CmsCategory::query()->findOrFail($post->category_id);

        $expectedKeys = [
            'cms-page' => sprintf('cms_page.%d.title', $page->id),
            'cms-post' => sprintf('cms_post.%d.title', $post->id),
            'cms-category' => sprintf('cms_category.%d.name', $category->id),
        ];

        foreach ($expectedKeys as $entity => $expectedKey) {
            $response = $this->getJson(sprintf(
                '/admin/api/themes/TH0001/translations?locale=en&group=content&entity=%s&per_page=100',
                $entity,
            ))
                ->assertOk()
                ->assertJsonPath('data.theme_key', 'TH0001')
                ->assertJsonPath('data.locale', 'en')
                ->assertJsonPath('data.group', 'content')
                ->assertJsonPath('data.available_groups.0', 'static')
                ->assertJsonPath('data.available_groups.1', 'content')
                ->assertJsonPath('data.supported_locales.0', 'vi')
                ->assertJsonPath('data.supported_locales.1', 'en');

            $translationKeys = collect($response->json('data.entries'))->pluck('key');

            $this->assertTrue($translationKeys->contains($expectedKey));
        }
    }

    public function test_admin_can_save_cms_business_content_overrides_and_storefront_renders_them(): void
    {
        $this->bootstrapThemeDemoStorefront();

        $page = CmsPage::query()->where('slug', 'demo-th0001-gioi-thieu')->firstOrFail();
        $post = CmsPost::query()->with('category')->where('status', 'published')->whereNotNull('category_id')->firstOrFail();
        $category = $post->category;

        $pageTitle = 'About Mobile Hub QA';
        $postTitle = 'Launch Story QA';
        $categoryName = 'Campaign Updates QA';

        $this->putJson('/admin/api/themes/TH0001/translations/en', [
            'locale' => 'en',
            'group' => 'content',
            'entries' => [
                ['key' => sprintf('cms_page.%d.title', $page->id), 'value' => $pageTitle],
                ['key' => sprintf('cms_post.%d.title', $post->id), 'value' => $postTitle],
                ['key' => sprintf('cms_category.%d.name', $category->id), 'value' => $categoryName],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.theme_key', 'TH0001')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.group', 'content');

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => sprintf('cms_page.%d.title', $page->id),
            'value' => $pageTitle,
        ]);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => sprintf('cms_post.%d.title', $post->id),
            'value' => $postTitle,
        ]);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => sprintf('cms_category.%d.name', $category->id),
            'value' => $categoryName,
        ]);

        $this->assertDatabaseHas('content_translations', [
            'website_key' => 'website-main',
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'locale' => 'en',
            'slug' => $post->slug,
            'translation_status' => 'published',
        ]);
        $this->assertDatabaseHas('localized_routes', [
            'website_key' => 'website-main',
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'locale' => 'en',
            'is_published' => true,
        ]);

        $this->get(route('site.pages.show', ['locale' => 'en', 'slug' => $page->slug]))
            ->assertOk()
            ->assertSee($pageTitle);

        $this->get(route('site.blog.show', ['locale' => 'en', 'slug' => $post->slug]))
            ->assertOk()
            ->assertSee($postTitle)
            ->assertSee($categoryName);

        $this->get(route('site.blog.index', ['locale' => 'en']))
            ->assertOk()
            ->assertSee($categoryName);
    }

    public function test_admin_can_filter_and_paginate_group_content_entries_for_non_cms_entities(): void
    {
        $this->bootstrapThemeDemoStorefront();

        $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&keyword=site_profile&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.keyword', 'site_profile')
            ->assertJsonPath('data.pagination.page', 1)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.entries.0.key', 'site_profile.site_name');

        $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&entity=menu&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.entity', 'menu')
            ->assertJsonPath('data.entries.0.key', 'cms_menu.primary-navigation.0.label');

        $catalogProductResponse = $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&entity=catalog-product&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.entity', 'catalog-product')
            ->assertJson(fn ($json) => $json->whereAllType([
                'data.entries.0.key' => 'string',
            ]));

        $availableEntities = collect($catalogProductResponse->json('data.available_entities'));
        $this->assertTrue($availableEntities->contains('catalog-category'));
        $this->assertTrue($availableEntities->contains('catalog-product'));

        $catalogResponse = $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&entity=catalog&per_page=5')
            ->assertOk();

        $catalogKeys = collect($catalogResponse->json('data.entries'))->pluck('key');
        $this->assertTrue($catalogKeys->every(fn (string $key): bool => str_starts_with($key, 'catalog_category.') || str_starts_with($key, 'catalog_product.')));

        $response = $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&keyword=catalog_product.&per_page=2&page=2')
            ->assertOk();

        $entries = collect($response->json('data.entries'));

        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn (array $entry): bool => str_starts_with((string) $entry['key'], 'catalog_product.')));
        $this->assertSame(2, $response->json('data.pagination.page'));
        $this->assertGreaterThan(2, $response->json('data.pagination.total'));
    }

    public function test_admin_can_filter_group_content_entries_for_granular_cms_entities(): void
    {
        $this->bootstrapThemeDemoStorefront();

        $pageResponse = $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&entity=cms-page&per_page=20')
            ->assertOk();

        $pageKeys = collect($pageResponse->json('data.entries'))->pluck('key');
        $this->assertNotEmpty($pageKeys);
        $this->assertTrue($pageKeys->every(fn (string $key): bool => str_starts_with($key, 'cms_page.')));

        $postResponse = $this->getJson('/admin/api/themes/TH0001/translations?locale=en&group=content&entity=cms-post&per_page=20')
            ->assertOk();

        $postKeys = collect($postResponse->json('data.entries'))->pluck('key');
        $this->assertNotEmpty($postKeys);
        $this->assertTrue($postKeys->every(fn (string $key): bool => str_starts_with($key, 'cms_post.')));
    }

    public function test_admin_can_save_non_cms_content_overrides_and_storefront_renders_them(): void
    {
        $this->bootstrapThemeDemoStorefront();

        $heroBanner = SiteBanner::query()->where('placement', 'hero-main')->orderBy('id')->firstOrFail();
        $category = CatalogCategory::query()->whereNull('parent_id')->where('is_active', true)->orderBy('id')->firstOrFail();
        $productCategoryId = $category->children()->value('id') ?? $category->id;
        $product = CatalogProduct::query()->with('category')->where('catalog_category_id', $productCategoryId)->where('is_active', true)->orderBy('id')->firstOrFail();
        $category = $product->category ?? $category;

        $companyName = 'AIO Storefront QA';
        $menuLabel = 'Stories QA';
        $bannerTitle = 'Hero Campaign QA';
        $categoryName = 'Phones Category QA';
        $productName = 'Flagship Product QA';

        $this->putJson('/admin/api/themes/TH0001/translations/en', [
            'locale' => 'en',
            'group' => 'content',
            'entries' => [
                ['key' => 'branding.company_name', 'value' => $companyName],
                ['key' => 'cms_menu.primary-navigation.0.label', 'value' => $menuLabel],
                ['key' => sprintf('site_banner.%d.title', $heroBanner->id), 'value' => $bannerTitle],
                ['key' => sprintf('catalog_category.%d.name', $category->id), 'value' => $categoryName],
                ['key' => sprintf('catalog_product.%d.name', $product->id), 'value' => $productName],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'branding.company_name',
            'value' => $companyName,
        ]);
        $siteProfile = SiteProfile::query()->forWebsite('website-main')->firstOrFail();
        $profileTranslation = ContentTranslation::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', 'website-main')
            ->where('resource_type', 'site_profile')
            ->where('resource_id', (string) $siteProfile->id)
            ->where('locale', 'en')
            ->firstOrFail();
        $this->assertSame($companyName, data_get($profileTranslation->payload, 'branding.company_name'));
        $this->assertSame('published', $profileTranslation->translation_status->value);
        app()->setLocale('en');
        $this->assertSame(
            $companyName,
            app(BusinessContentTranslationService::class)->text(
                'website-main',
                'branding.company_name',
                'AIO Tech Market',
            ),
        );

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'cms_menu.primary-navigation.0.label',
            'value' => $menuLabel,
        ]);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => sprintf('site_banner.%d.title', $heroBanner->id),
            'value' => $bannerTitle,
        ]);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => sprintf('catalog_category.%d.name', $category->id),
            'value' => $categoryName,
        ]);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => sprintf('catalog_product.%d.name', $product->id),
            'value' => $productName,
        ]);

        $homeResponse = $this->get(route('site.home', FrontendLocalization::routeParameterDefaults('en')))
            ->assertOk();
        $homeResponse
            ->assertSee($companyName)
            ->assertSee($menuLabel)
            ->assertSee($bannerTitle);

        $this->get(route('site.catalog.category', array_merge(FrontendLocalization::routeParameterDefaults('en'), ['slug' => $category->slug])))
            ->assertOk()
            ->assertSee($categoryName);

        $this->get(route('site.catalog.product', array_merge(FrontendLocalization::routeParameterDefaults('en'), ['slug' => $product->slug])))
            ->assertOk()
            ->assertSee($productName);
    }

    private function bootstrapThemeDemoStorefront(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/themes/TH0001/activate')->assertOk();
        $this->postJson('/admin/api/themes/TH0001/demo-data', [
            'preset' => 'electronics-superstore',
        ])->assertOk();
    }
}
