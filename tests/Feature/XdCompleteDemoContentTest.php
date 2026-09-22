<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\Demo\XdCompleteDemoContentProvider;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\ThemeDemoRecord;
use App\Support\LegacyTextEncoding;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class XdCompleteDemoContentTest extends TestCase
{
    use RefreshDatabase;

    public static function themes(): array
    {
        $definitions = json_decode(file_get_contents(__DIR__.'/../../resources/demo/xd-themes.json'), true);

        return array_map(fn ($key, $brief) => [$key, $brief['preset']], array_keys($definitions), array_values($definitions));
    }

    #[DataProvider('themes')]
    public function test_complete_demo_is_repeatable_and_links_render(string $key, string $preset): void
    {
        $custom = CmsService::create(['title' => 'Dịch vụ riêng', 'slug' => 'custom-service', 'status' => 'draft']);
        $generator = app(ThemeDemoContentGenerator::class);
        foreach ([1, 2] as $run) {
            $result = $generator->generate($key, $preset);
            $this->assertSame(4, $result['counts']['services']);
            $this->assertSame(3, CatalogProduct::count());
            $this->assertSame(3, CmsPost::count());
            $this->assertSame(3, CmsProject::count());
            $this->assertSame(5, CmsService::count());
        }
        foreach (CatalogProduct::all() as $product) {
            $this->assertFileExists(public_path($product->image_url));
        }
        $errors = [];
        $menu = CmsMenu::where('name', $key.' Main Menu')->firstOrFail();
        $urls = array_column($menu->items, 'url');
        $urls[] = route('site.catalog.product', ['locale' => 'vi', 'slug' => CatalogProduct::first()->slug]);
        $urls[] = route('site.services.show', ['locale' => 'vi', 'slug' => CmsService::where('status', 'published')->first()->slug]);
        $urls[] = route('site.projects.show', ['locale' => 'vi', 'slug' => CmsProject::first()->slug]);
        foreach ($urls as $url) {
            $response = $this->get($url);
            if ($response->status() >= 400) {
                $errors[] = $url.' => '.$response->status();
            }
            if (getenv('XD_DEMO_PREVIEW') && $url === $urls[0]) {
                file_put_contents(storage_path('framework/testing/'.$key.'-home.html'), $response->getContent());
            }
        }
        $this->assertSame([], $errors, $key.' broken routes: '.implode(', ', $errors));
        $generator->delete($key);
        $this->assertDatabaseHas('cms_services', ['id' => $custom->id]);
        $this->assertSame(0, ThemeDemoRecord::where('theme_key', $key)->count());
        $this->assertSame(0, CatalogProduct::count());
    }

    public function test_reset_reuses_unmarked_menu_with_same_unique_key_without_claiming_it(): void
    {
        $menu = CmsMenu::create(['name' => 'XD0313 Main Menu', 'location' => 'primary-navigation',
            'items' => [['label' => 'Manual link', 'url' => '/vi/contact', 'link_type' => 'contact']]]);
        $original = $menu->fresh()->getRawOriginal('items');
        $other = CmsMenu::create(['website_key' => 'other-site', 'name' => 'XD0313 Main Menu', 'location' => 'primary-navigation', 'items' => []]);
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0313');
        $generator = app(ThemeDemoContentGenerator::class);
        foreach ([1, 2] as $run) {
            $generator->generate('XD0313', $provider->defaultPreset(), true);
            $this->assertSame(1, CmsMenu::where('name', 'XD0313 Main Menu')->count());
            $this->assertSame($original, $menu->fresh()->getRawOriginal('items'));
            $this->assertFalse(ThemeDemoRecord::where('model_type', CmsMenu::class)->where('model_id', $menu->id)->exists());
            $this->assertDatabaseHas('cms_menus', ['id' => $other->id, 'website_key' => 'other-site']);
        }
        $generator->delete('XD0313');
        $this->assertDatabaseHas('cms_menus', ['id' => $menu->id]);
    }

    public function test_all_xd_themes_have_a_provider_and_valid_local_images(): void
    {
        $registry = app(ThemeDemoContentProviderRegistry::class);
        foreach (glob(base_path('themes/XD*'), GLOB_ONLYDIR) as $directory) {
            $this->assertNotNull($registry->forTheme(basename($directory)), basename($directory));
        }
        foreach (XdCompleteDemoContentProvider::definitions() as $key => $brief) {
            for ($i = 1; $i <= 3; $i++) {
                $path = public_path('theme-demo/xd-shared/'.$brief['image_group'].'-'.$i.'.jpg');
                $this->assertFileExists($path);
                $this->assertNotFalse(getimagesize($path));
            }
            foreach (array_filter($brief['product_images']) as $path) {
                $this->assertFileExists(public_path($path));
            }
            $json = json_encode($brief, JSON_UNESCAPED_UNICODE);
            $this->assertSame($json, app(LegacyTextEncoding::class)->repair($json), $key);
        }
    }

    public function test_demo_deletion_is_scoped_to_website_and_preserves_other_themes(): void
    {
        $context = app(SiteContext::class);
        $generator = app(ThemeDemoContentGenerator::class);
        $context->set(null, 'xd-site-one');
        $generator->generate('XD0302', 'xd0302-solar-energy');
        $ids = CatalogProduct::pluck('id')->all();
        $context->set(null, 'xd-site-two');
        $generator->generate('XD0302', 'xd0302-solar-energy');
        $generator->generate('XD0308', 'xd0308-study-abroad');
        $generator->delete('XD0302');
        $this->assertSame(3, CatalogProduct::count());
        $this->assertTrue(ThemeDemoRecord::where('theme_key', 'XD0308')->exists());
        $context->set(null, 'xd-site-one');
        $this->assertSame($ids, CatalogProduct::pluck('id')->all());
        $context->set(null);
    }
}
