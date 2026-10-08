<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Database\Seeders\Nt502DemoMediaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Nt502DemoMediaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_repairs_legacy_demo_without_resetting_records_and_fills_the_fifth_news_slot_once(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NT502')->generate('nt502-dola-furniture');
        $productIds = CatalogProduct::pluck('id')->all();
        $categoryIds = CatalogCategory::pluck('id')->all();
        foreach ([CatalogCategory::class, CatalogProduct::class, SiteBanner::class, CmsTestimonial::class] as $model) {
            $model::query()->update(['image_url' => 'https://images.unsplash.com/1600210492486-724fe5c67fb0?auto=format&fit=crop']);
        }
        CmsMedia::query()->update(['file_url' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop']);
        $fifth = CmsPost::where('slug', Str::slug('nt502-Chọn bàn ăn phù hợp cho căn hộ nhỏ'))->firstOrFail();
        $mediaId = $fifth->featured_media_id;
        ThemeDemoRecord::where('model_type', CmsPost::class)->where('model_id', $fifth->id)->delete();
        ThemeDemoRecord::where('model_type', CmsMedia::class)->where('model_id', $mediaId)->delete();
        $fifth->delete();
        CmsMedia::whereKey($mediaId)->delete();
        $this->assertSame(4, CmsPost::count());
        $product = CatalogProduct::firstOrFail();
        $product->update(['price' => 1234567, 'detail_content' => '<p>Nội dung đã chỉnh</p>']);

        $this->seed(Nt502DemoMediaSeeder::class);
        $recordCount = ThemeDemoRecord::count();
        $this->seed(Nt502DemoMediaSeeder::class);

        $this->assertSame($productIds, CatalogProduct::pluck('id')->all());
        $this->assertSame($categoryIds, CatalogCategory::pluck('id')->all());
        $this->assertSame('1234567.00', $product->fresh()->price);
        $this->assertSame('<p>Nội dung đã chỉnh</p>', $product->fresh()->detail_content);
        $this->assertSame(5, CmsPost::count());
        $this->assertSame(5, CmsMedia::count());
        $this->assertSame($recordCount, ThemeDemoRecord::count());
        foreach ([CatalogCategory::class, CatalogProduct::class, SiteBanner::class, CmsTestimonial::class] as $model) {
            foreach ($model::all() as $record) {
                $this->assertStringStartsWith('/theme-demo/', $record->image_url);
                $this->assertFileExists(public_path($record->image_url));
                $this->assertNotFalse(getimagesize(public_path($record->image_url)));
            }
        }
        $images = CmsMedia::pluck('file_url');
        $this->assertCount(5, $images->unique());
        foreach ($images as $image) {
            $this->assertFileExists(public_path($image));
        }
        $response = $this->get('/vi')->assertOk();
        $this->assertStringNotContainsString('images.unsplash.com', $response->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertSame(5, (new \DOMXPath($dom))->query('//section[@id="tin-tuc"]//div[@class="n502-news"]//a')->length);
    }

    public function test_keeps_custom_images_untracked_records_and_other_websites_unchanged(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NT502')->generate('nt502-dola-furniture');
        $product = CatalogProduct::firstOrFail();
        $product->update(['image_url' => 'https://example.test/custom-sofa.webp']);
        $manual = CatalogProduct::create(['name' => 'Sản phẩm nhập tay', 'slug' => 'manual-nt502', 'sku' => 'MANUAL-NT502', 'price' => 100, 'image_url' => 'https://images.unsplash.com/123-manual']);
        $media = CmsMedia::firstOrFail();
        $media->update(['file_path' => 'manual/photo.webp', 'file_url' => 'https://images.unsplash.com/123-upload']);
        SiteProfile::create(['website_key' => 'other', 'active_theme_key' => 'NT501']);
        $other = CatalogProduct::create(['website_key' => 'other', 'name' => 'Other website', 'slug' => 'other-product', 'sku' => $product->sku, 'price' => 200, 'image_url' => 'https://images.unsplash.com/123-other']);
        ThemeDemoRecord::create(['website_key' => 'other', 'theme_key' => 'NT502', 'preset_key' => 'nt502-dola-furniture', 'model_type' => CatalogProduct::class, 'model_id' => $other->id]);

        $this->seed(Nt502DemoMediaSeeder::class);

        $this->assertSame('https://example.test/custom-sofa.webp', $product->fresh()->image_url);
        $this->assertSame('https://images.unsplash.com/123-manual', $manual->fresh()->image_url);
        $this->assertSame('manual/photo.webp', $media->fresh()->getRawOriginal('file_path'));
        $this->assertSame('https://images.unsplash.com/123-upload', $media->fresh()->getRawOriginal('file_url'));
        $this->assertSame('https://images.unsplash.com/123-other', $other->fresh()->image_url);
    }

    public function test_repairs_another_nt502_website_and_preserves_a_manual_article_with_the_new_slug(): void
    {
        SiteProfile::create(['website_key' => 'nt502-demo', 'active_theme_key' => 'NT502']);
        $product = CatalogProduct::create(['website_key' => 'nt502-demo', 'name' => 'Sofa demo', 'slug' => 'demo-sofa', 'sku' => 'NT502-01', 'price' => 100, 'image_url' => 'https://images.unsplash.com/123-sofa']);
        ThemeDemoRecord::create(['website_key' => 'nt502-demo', 'theme_key' => 'NT502', 'preset_key' => 'nt502-dola-furniture', 'model_type' => CatalogProduct::class, 'model_id' => $product->id]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NT502')->generate('nt502-dola-furniture');
        $article = CmsPost::where('slug', Str::slug('nt502-Chọn bàn ăn phù hợp cho căn hộ nhỏ'))->firstOrFail();
        ThemeDemoRecord::where('model_type', CmsPost::class)->where('model_id', $article->id)->delete();
        $article->update(['title' => 'Bài nhập tay', 'status' => 'draft', 'body' => '<p>Giữ nguyên</p>']);

        $this->seed(Nt502DemoMediaSeeder::class);

        $this->assertSame('/theme-demo/ec915/product-sofa-ivory.webp', $product->fresh()->image_url);
        $this->assertSame('Bài nhập tay', $article->fresh()->title);
        $this->assertSame('draft', $article->fresh()->status);
        $this->assertSame('<p>Giữ nguyên</p>', $article->fresh()->body);
        $this->assertSame(5, CmsPost::count());
        $this->assertFalse(ThemeDemoRecord::where('model_type', CmsPost::class)->where('model_id', $article->id)->exists());
    }
}
