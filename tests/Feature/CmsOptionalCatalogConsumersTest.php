<?php

namespace Tests\Feature;

use App\Core\Cms\CmsMenuLinkRegistry;
use App\Core\Cms\CmsMenuLinkTargetResolver;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\ModuleInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsOptionalCatalogConsumersTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_promotion_editors_keep_cms_link_options_without_catalog_tables(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]), 'admin');
        $page = CmsPage::query()->create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $category = CmsCategory::query()->create(['name' => 'News', 'slug' => 'news']);
        $this->removeCatalogTables();

        foreach (['featured-categories', 'side-promos'] as $editor) {
            $this->getJson('/admin/api/cms/'.$editor)->assertOk()
                ->assertJsonPath('data.linkOptions.productCategories', [])
                ->assertJsonPath('data.linkOptions.pages.0.value', (string) $page->id)
                ->assertJsonPath('data.linkOptions.postCategories.0.value', (string) $category->id);
        }
    }

    public function test_legacy_menu_urls_still_resolve_cms_targets_without_catalog_tables(): void
    {
        $post = CmsPost::query()->create([
            'title' => 'Legacy article', 'slug' => 'legacy-article', 'status' => 'published',
        ]);
        DB::table('localized_routes')->where('resource_type', 'cms_post')->where('resource_id', $post->id)->delete();
        $this->removeCatalogTables();

        $resolver = app(CmsMenuLinkTargetResolver::class);
        $this->assertSame([
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
        ], $resolver->identity([], 'website-main', 'vi', '/n/legacy-article'));
        $this->assertNull($resolver->identity([], 'website-main', 'vi', '/unknown-local-link'));
    }

    public function test_stale_catalog_menu_targets_return_validation_errors_without_catalog_tables(): void
    {
        $page = CmsPage::query()->create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $this->removeCatalogTables();
        $registry = app(CmsMenuLinkRegistry::class);
        $registry->assertValidTargets([
            ['link_type' => 'page', 'link_value' => (string) $page->id],
        ], 'website-main');

        foreach (['product', 'product-category'] as $linkType) {
            try {
                $registry->assertValidTargets([
                    ['link_type' => $linkType, 'link_value' => '1'],
                ], 'website-main');
                $this->fail('A missing optional Catalog target must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items.0.link_value', $exception->errors());
            }
        }
    }

    public function test_promotion_editors_retain_existing_catalog_options_when_schema_is_present(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]), 'admin');
        ModuleInstallation::query()->updateOrCreate(['key' => 'catalog'], [
            'name' => 'Catalog', 'version' => '0.2.1', 'status' => 'disabled',
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Coffee', 'slug' => 'coffee']);

        foreach (['featured-categories', 'side-promos'] as $editor) {
            $this->getJson('/admin/api/cms/'.$editor)->assertOk()
                ->assertJsonPath('data.linkOptions.productCategories.0.value', (string) $category->id);
        }

        app(CmsMenuLinkRegistry::class)->assertValidTargets([
            ['link_type' => 'product-category', 'link_value' => (string) $category->id],
        ], 'website-main');
    }

    private function removeCatalogTables(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            Schema::drop('catalog_product_images');
            Schema::drop('catalog_products');
            Schema::drop('catalog_categories');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
