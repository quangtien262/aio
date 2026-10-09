<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsTestimonial;
use App\Models\CmsService;
use App\Models\LandingPageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0308HomeBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_reviews_are_seeded_without_duplicates_and_render_in_the_carousel(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0308');
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0308', $provider->defaultPreset());
        // An existing landing page must also pick up the larger CMS source limit.
        LandingPageBlock::where('block_type', 'testimonials')->firstOrFail()->update(['settings' => ['source' => 'cms_testimonials', 'limit' => 2]]);
        LandingPageBlock::where('block_type', 'featured_services')->firstOrFail()->update(['settings' => ['source' => 'cms_services', 'limit' => 3]]);
        $generator->generate('XD0308', $provider->defaultPreset());

        $this->assertSame(6, CmsTestimonial::count());
        $this->assertSame(6, CmsService::count());
        $this->assertSame(6, data_get(LandingPageBlock::where('block_type', 'testimonials')->firstOrFail()->settings, 'limit'));
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, $xpath->query('//section[@id="danh-gia"]//article[contains(@class,"xd8-review-card")]')->length);
        $this->assertSame(6, $xpath->query('//section[@id="dich-vu"]//article')->length);
        $this->assertSame(2, $xpath->query('//img[contains(@class,"xd8-section-background")]')->length);
        $cards = $xpath->query('//section[@id="quoc-gia"]//a[contains(@class,"xd8-discovery-card")]');
        $this->assertGreaterThanOrEqual(4, $cards->length);
        foreach ($cards as $card) {
            $this->assertSame(1, $xpath->query('.//h3', $card)->length);
            $this->assertSame(1, $xpath->query('.//img', $card)->length);
            $this->assertNotSame('#', $card->getAttribute('href'));
        }
        $response->assertSee('Khám phá lộ trình du học của bạn');
        $response->assertSee('Học viên · phản hồi minh họa');
        $response->assertSee('Danh sách trường được giải thích', false);
        if ($preview = getenv('XD0308_BLOCK_PREVIEW')) {
            file_put_contents($preview, $response->getContent());
        }
    }
}
