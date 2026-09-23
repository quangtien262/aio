<?php

namespace Tests\Feature;

use App\Models\CmsService;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dl750ServiceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_detail_renders_content_gallery_and_configured_contacts(): void
    {
        SiteProfile::create([
            'site_name' => 'Forest Camp', 'website_type' => 'ecommerce', 'active_theme_key' => 'DL750',
            'branding' => ['support_hotline' => '0887 750 750', 'support_email' => 'hello@forest.test'],
        ]);
        $service = CmsService::create([
            'title' => 'Thuê lều trại', 'slug' => 'thue-leu-trai', 'status' => 'published', 'publish_at' => now(),
            'summary' => 'Chuẩn bị không gian nghỉ ngơi giữa thiên nhiên cho hành trình của bạn.',
            'content' => '<h2>Không gian cho những chuyến đi</h2><p>Chọn trang bị phù hợp với số người và lịch trình.</p><h2>Thông tin bàn giao</h2><p>Trao đổi phương án nhận và trả thiết bị khi tư vấn.</p>',
        ]);
        $service->images()->create(['image_url' => '/theme-demo/xd-shared/travel-3.jpg', 'alt_text' => 'Hành trình ngoài trời', 'is_featured' => true]);
        $service->images()->create(['image_url' => '/theme-demo/xd-shared/travel-1.jpg', 'alt_text' => 'Khung cảnh chuyến đi']);
        $response = $this->get(route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]))
            ->assertOk()->assertSee('id="dl-service-title"', false)
            ->assertSee('Chuẩn bị không gian nghỉ ngơi giữa thiên nhiên cho hành trình của bạn.')
            ->assertSee('Không gian cho những chuyến đi')->assertSee('Thông tin bàn giao')
            ->assertSee('id="dl-service-gallery"', false)->assertSee('Khung cảnh chuyến đi')
            ->assertSee('tel:0887750750', false)->assertSee('mailto:hello@forest.test', false)
            ->assertSee(route('site.contact', ['locale' => 'vi']), false)
            ->assertSee(route('site.services.index', ['locale' => 'vi']), false);

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $this->assertSame(2, $xpath->query('//div[@class="dl-service-gallery-grid"]/a')->length);
        if (getenv('DL750_SERVICE_PREVIEW')) {
            file_put_contents(getenv('DL750_SERVICE_PREVIEW'), $response->getContent());
        }
    }

    public function test_service_without_images_or_content_has_usable_fallback(): void
    {
        SiteProfile::create(['site_name' => 'Forest Camp', 'website_type' => 'ecommerce', 'active_theme_key' => 'DL750']);
        $service = CmsService::create(['title' => 'Tư vấn hành trình', 'slug' => 'tu-van', 'status' => 'published', 'publish_at' => now()]);
        $response = $this->get(route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]))
            ->assertOk()->assertSee('dl-service-hero-text', false)
            ->assertSee('Liên hệ để được tư vấn chi tiết về dịch vụ và phương án phù hợp với nhu cầu của bạn.')
            ->assertDontSee('id="dl-service-gallery"', false)->assertDontSee('href="tel:', false)
            ->assertDontSee('href="mailto:', false);
        if (getenv('DL750_SERVICE_PREVIEW')) {
            file_put_contents(getenv('DL750_SERVICE_PREVIEW').'.empty.html', $response->getContent());
        }
    }
}
