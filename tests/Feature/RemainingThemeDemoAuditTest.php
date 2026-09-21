<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\ThemeDemoRecord;
use App\Support\FrontendRouteUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RemainingThemeDemoAuditTest extends TestCase
{
    use RefreshDatabase;

    public static function themes(): array
    {
        $items = [];
        foreach (glob(__DIR__.'/../../themes/*/theme.json') as $path) {
            $manifest = json_decode(file_get_contents($path), true);
            if (! str_starts_with(strtoupper($manifest['key']), 'XD')) {
                $items[$manifest['key']] = [$manifest['key'], $manifest['website_type'] ?? 'ecommerce'];
            }
        }

        return $items;
    }

    #[DataProvider('themes')]
    public function test_demo_generation_and_public_routes(string $key, string $type): void
    {
        $this->withoutExceptionHandling();
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->defaultPresetForTheme($key) ?? ($type === 'service' ? 'ser-airport-city' : 'electronics-superstore');
        $custom = CmsPage::create(['title' => 'Nội dung riêng', 'slug' => 'custom-content', 'status' => 'draft']);
        $errors = [];
        $report = ['theme' => $key, 'preset' => $preset];
        try {
            $result = $generator->generate($key, $preset);
            $count = ThemeDemoRecord::where('theme_key', $key)->count();
            $generator->generate($key, $preset);
            $this->assertSame($count, ThemeDemoRecord::where('theme_key', $key)->count(), $key.' duplicate records');
            $this->assertNotNull($custom->fresh());
            $report['counts'] = $result['counts'];
            $ids = ThemeDemoRecord::where('theme_key', $key)->where('model_type', CmsMenu::class)->pluck('model_id');
            $menus = CmsMenu::whereKey($ids)->get();
            $urls = [route('site.home', ['locale' => 'vi'])];
            foreach ($menus as $menu) {
                foreach ($menu->items ?? [] as $item) {
                    $url = $item['url'] ?? '';
                    if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
                        $urls[] = $url;
                    }
                }
            }
            if ($product = CatalogProduct::first()) {
                $urls[] = route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]);
            }
            if ($post = CmsPost::first()) {
                $urls[] = route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]);
            }
            if ($service = CmsService::first()) {
                $urls[] = route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]);
            }
            foreach (array_unique($urls) as $url) {
                $url = FrontendRouteUrl::localized($url);
                try {
                    $response = $this->get($url);
                    if ($response->status() >= 400) {
                        $errors[] = $url.': HTTP '.$response->status();
                    }
                } catch (\Throwable $e) {
                    $errors[] = $url.': '.$e->getMessage();
                }
            }
            $report['missing_images'] = [];
            foreach (CatalogProduct::all() as $product) {
                if (str_starts_with($product->image_url ?? '', '/') && ! is_file(public_path($product->image_url))) {
                    $report['missing_images'][] = $product->image_url;
                }
            }
            $generator->delete($key);
            $this->assertNotNull($custom->fresh());
        } catch (\Throwable $e) {
            $errors[] = 'generation/delete: '.$e->getMessage();
            $report['trace'] = substr($e->getTraceAsString(), 0, 4000);
        }
        $report['errors'] = $errors;
        file_put_contents(storage_path('framework/testing/demo-audit-'.strtolower($key).'.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->assertSame([], $errors, $key.': '.implode("\n", $errors));
        $this->assertSame([], $report['missing_images'] ?? []);
    }
}
