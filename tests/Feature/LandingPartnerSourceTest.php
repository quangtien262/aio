<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsServiceCategory;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPartnerSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_partner_source_offers_the_three_category_sources_with_clear_labels(): void
    {
        $builder = app(LandingPageBuilder::class);
        $checked = 0;

        foreach (app(ThemeRegistry::class)->all() as $theme) {
            foreach ($builder->availableBlocks($theme['key']) as $block) {
                $options = collect(data_get($block, 'settings_schema.source.options', []));
                if (! $options->contains('value', 'cms_partners')) {
                    continue;
                }

                $labels = $options->pluck('label', 'value');
                $this->assertSame('Đối tác', $labels['cms_partners'], $theme['key']);
                $this->assertSame('Danh mục sản phẩm', $labels['catalog_categories']);
                $this->assertSame('Danh mục dịch vụ', $labels['cms_service_categories']);
                $this->assertSame('Danh mục tin tức', $labels['cms_categories']);
                $this->assertTrue($labels->has('custom'));
                $this->assertSame($options->count(), $labels->count());
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_partner_blocks_read_the_selected_category_source_without_leaking_another_website(): void
    {
        $websiteKey = 'partner-source-test';
        app(SiteContext::class)->set(null, $websiteKey);
        $sources = [
            'catalog_categories' => [CatalogCategory::class, 'Sản phẩm kiểm thử'],
            'cms_service_categories' => [CmsServiceCategory::class, 'Dịch vụ kiểm thử'],
            'cms_categories' => [CmsCategory::class, 'Tin tức kiểm thử'],
        ];

        foreach ($sources as $source => [$model, $name]) {
            $attributes = ['website_key' => $websiteKey, 'name' => $name, 'slug' => $source];
            if ($model !== CmsCategory::class) {
                $attributes['is_active'] = true;
            }
            $model::query()->create($attributes);
            $model::query()->create([
                ...$attributes,
                'website_key' => 'another-website',
                'name' => 'Không được xuất hiện',
                'slug' => $source.'-other',
            ]);
        }

        $builder = app(LandingPageBuilder::class);
        foreach (['AUTO850' => 'auto850_brands', 'DN202' => 'partner_logos', 'TOOL751' => 'tool751_partners'] as $theme => $blockType) {
            $page = $builder->seedHome($websiteKey, $theme);
            $block = $page->blocks()->where('block_type', $blockType)->firstOrFail();

            foreach ($sources as $source => [$model, $name]) {
                $block->update(['settings' => ['source' => $source, 'limit' => 6]]);
                $items = $builder->previewDynamicItems($block, 'vi');

                $this->assertCount(1, $items, $theme.': '.$source);
                $this->assertSame($name, $items[0]['title']);
                $this->assertNotEmpty($items[0]['url']);
                $this->assertNotEmpty($items[0]['image']);
                if ($blockType !== 'auto850_brands') {
                    $this->assertSame($name, $items[0]['name']);
                    $this->assertSame($items[0]['url'], $items[0]['href']);
                    $this->assertSame($items[0]['image'], $items[0]['logo']);
                }
            }

            $this->assertSame([], $builder->previewDynamicItems($block, 'vi', ['source' => 'custom']));
        }
    }
}
