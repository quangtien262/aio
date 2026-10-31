<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\Admin;
use App\Models\LandingPageBlock;
use App\Models\ModuleInstallation;
use App\Models\SiteBanner;
use App\Support\LandingPages\LandingPageBuilder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt502HeroEditingTest extends TestCase
{
    use RefreshDatabase;

    private function seedHero(): LandingPageBlock
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NT502')->generate('nt502-dola-furniture');
        ModuleInstallation::updateOrCreate(['key' => 'cms'], ['name' => 'CMS', 'version' => '1.0.0', 'status' => 'enabled']);
        $this->actingAs(Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]), 'admin');

        return LandingPageBlock::where('theme_key', 'NT502')->where('block_type', 'hero_slider')->firstOrFail();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    private function edit(LandingPageBlock $block, array $slides, bool $publish = true): void
    {
        $this->putJson(route('admin.api.landing.blocks.update', ['block' => $block->id]), [
            'locale' => 'vi', 'anchor_id' => 'top111', 'is_visible' => true,
            'settings' => $block->fresh()->settings,
            'data' => [
                'title' => 'Bộ sưu tập đã cập nhật', 'subtitle' => 'DOLA FURNITURE mới',
                'description' => 'Ưu đãi nội thất mới.', 'content' => ['slides' => $slides],
            ],
            'publish' => $publish,
        ])->assertOk();
    }

    public function test_legacy_banner_backed_hero_shows_saved_slides_and_removals_on_public_homepage(): void
    {
        $block = $this->seedHero();
        $block->update(['settings' => array_merge($block->settings, ['source' => 'site_banners'])]);
        $bannersBefore = SiteBanner::orderBy('id')->get()->toArray();
        $slides = [
            ['title' => 'Phòng khách sau chỉnh sửa', 'summary' => 'Nội dung slide một mới.', 'image' => '/theme-demo/ec915/hero-interior.webp', 'link_url' => '#phong-khach', 'button_label' => 'Khám phá phòng khách'],
            ['title' => 'Phòng ngủ sau chỉnh sửa', 'kicker' => 'Bộ sưu tập riêng', 'summary' => 'Nội dung slide hai mới.', 'image' => '/theme-demo/ec915/room-bedroom.webp', 'link_url' => '#phong-ngu', 'button_label' => 'Khám phá phòng ngủ'],
        ];
        $this->edit($block, $slides);
        $this->assertDatabaseHas('landing_page_block_data', ['landing_page_block_id' => $block->id, 'locale' => 'vi', 'translation_status' => 'published']);
        $xpath = $this->xpath($this->get('/vi')->assertOk()->getContent());
        $this->assertCount(1, $xpath->query('//section[@id="top111"][@data-n502-slider]'));
        $rendered = $xpath->query('//*[@data-n502-slide]');
        $this->assertCount(2, $rendered);
        foreach ($slides as $index => $slide) {
            $node = $rendered->item($index);
            $this->assertSame($slide['title'], $xpath->query('.//h1', $node)->item(0)->textContent);
            $this->assertSame($slide['summary'], $xpath->query('.//p', $node)->item(0)->textContent);
            $this->assertSame($slide['image'], $xpath->query('.//img', $node)->item(0)->getAttribute('src'));
            $this->assertSame($slide['link_url'], $xpath->query('.//a', $node)->item(0)->getAttribute('href'));
            $this->assertSame($slide['button_label'], $xpath->query('.//a', $node)->item(0)->textContent);
        }
        $this->assertStringContainsString('DOLA FURNITURE mới', $rendered->item(0)->textContent);
        $this->assertStringContainsString('Bộ sưu tập riêng', $rendered->item(1)->textContent);
        $this->edit($block, [$slides[1]]);
        $xpath = $this->xpath($this->get('/vi')->assertOk()->getContent());
        $this->assertCount(1, $xpath->query('//*[@data-n502-slide]'));
        $this->assertSame($slides[1]['title'], $xpath->query('//*[@data-n502-slide]//h1')->item(0)->textContent);
        $this->edit($block, []);
        $xpath = $this->xpath($this->get('/vi')->assertOk()->getContent());
        $this->assertCount(0, $xpath->query('//*[@data-n502-slider]'));
        $this->assertSame($bannersBefore, SiteBanner::orderBy('id')->get()->toArray());
    }

    public function test_quick_editor_preserves_locale_publication_flags_for_save_requests(): void
    {
        $this->seedHero();
        $html = $this->get('/vi?mod=admin')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/const editorLocales = (\[.*?\]);/s', $html, $matches));
        $options = collect(json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR));
        $this->assertTrue($options->firstWhere('code', 'vi')['is_published']);
        foreach ($options as $option) {
            $this->assertIsBool($option['is_published']);
        }
    }

    public function test_draft_slide_edits_stay_hidden_until_published(): void
    {
        $block = $this->seedHero();
        $slides = [['title' => 'Bản nháp riêng của slide', 'summary' => 'Chưa xuất bản.', 'image' => '/theme-demo/ec915/room-bedroom.webp']];
        $this->edit($block, $slides, false);
        $this->get('/vi')->assertOk()->assertDontSee($slides[0]['title']);
        $this->edit($block, $slides);
        $this->get('/vi')->assertOk()->assertSee($slides[0]['title']);
    }

    public function test_new_hero_uses_the_same_two_custom_slides_in_editor_and_storefront(): void
    {
        $block = $this->seedHero();
        $this->assertSame('custom', $block->settings['source']);
        $slides = json_decode($block->data()->where('locale', 'vi')->firstOrFail()->content, true)['slides'];
        $this->assertCount(2, $slides);
        $xpath = $this->xpath($this->get('/vi')->assertOk()->getContent());
        $this->assertCount(2, $xpath->query('//*[@data-n502-slide]'));
        foreach ($slides as $index => $slide) {
            $this->assertSame($slide['title'], $xpath->query('//*[@data-n502-slide]//h1')->item($index)->textContent);
        }
        $this->assertSame([], app(LandingPageBuilder::class)->previewDynamicItems($block, 'vi'));
    }

    public function test_editing_only_the_block_heading_updates_slide_one_and_keeps_slide_two(): void
    {
        $block = $this->seedHero();
        $previous = $block->data()->where('locale', 'vi')->firstOrFail();
        $content = json_decode($previous->content, true);
        $this->putJson(route('admin.api.landing.blocks.update', ['block' => $block->id]), [
            'locale' => 'vi', 'publish' => true,
            'data' => ['title' => 'Tiêu đề chung đã sửa', 'subtitle' => 'Nhãn phụ đã sửa', 'description' => 'Mô tả chung đã sửa', 'content' => $content],
        ])->assertOk()->assertJsonPath('data.data.content.slides.0.title', 'Tiêu đề chung đã sửa')
            ->assertJsonPath('data.data.content.slides.0.summary', 'Mô tả chung đã sửa');
        $xpath = $this->xpath($this->get('/vi')->assertOk()->getContent());
        $this->assertSame('Tiêu đề chung đã sửa', $xpath->query('//*[@data-n502-slide]//h1')->item(0)->textContent);
        $this->assertSame('Mô tả chung đã sửa', $xpath->query('//*[@data-n502-slide]//p')->item(0)->textContent);
        $this->assertSame($content['slides'][1]['title'], $xpath->query('//*[@data-n502-slide]//h1')->item(1)->textContent);
    }
}
