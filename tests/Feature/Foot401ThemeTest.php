<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use App\Models\CatalogProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot401ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_listing_and_detail_links_render(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('FOOT401', $generator->presetsForTheme('FOOT401')[0]['key']);
        $response = $this->get(route('site.projects.index', ['locale' => 'vi']))
            ->assertOk()->assertSee('foot-project-grid')->assertSee('Trao đổi với chúng tôi');
        if ($path = getenv('FOOT401_PROJECTS_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $links = (new \DOMXPath($dom))->query('//a[@class="foot-project-link"]');
        $this->assertGreaterThan(0, $links->length);
        foreach ($links as $link) {
            $this->get($link->getAttribute('href'))->assertOk();
        }
    }

    public function test_product_page_renders_themed_breadcrumb(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('FOOT401', $generator->presetsForTheme('FOOT401')[0]['key']);
        $product = CatalogProduct::firstOrFail();
        $response = $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))
            ->assertOk()->assertSee('foot-product-page')->assertSee('aria-current="page"', false);
        if ($path = getenv('FOOT401_PRODUCT_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }

    public function test_foot401_storefront_admin_mode_renders_landing_block_editor(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('FOOT401', $generator->presetsForTheme('FOOT401')[0]['key']);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']));

        $response
            ->assertOk()
            ->assertSee('family=Fraunces', false)
            ->assertSee("--foot-serif:'Fraunces'", false)
            ->assertSee('data-xd-editor', false)
            ->assertSee('data-xd-edit-block', false)
            ->assertSee('Sửa khối');

        $this->assertSame(6, substr_count($response->getContent(), 'data-xd-edit-block='));
        $this->assertSame(6, substr_count($response->getContent(), 'data-landing-block-id='));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertDontSee('data-xd-editor', false)
            ->assertDontSee('data-xd-edit-block', false);
    }
}
