<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\ContentTranslation;
use App\Models\WebsiteLocale;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedRouteRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Xd0313ServiceDetailTest extends TestCase
{
    use RefreshDatabase;

    private function generateDemo(): CmsService
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0313', $generator->presetsForTheme('XD0313')[0]['key']);

        return CmsService::where('slug', 'xd0313-tu-van-ho-so-du-lich')->firstOrFail();
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    public function test_detail_has_ten_scoped_public_services_and_news_with_a_useful_contact_form(): void
    {
        $service = $this->generateDemo();
        for ($i = 0; $i < 12; $i++) {
            CmsService::create(['title' => "Public service {$i}", 'slug' => "public-service-{$i}", 'status' => 'published', 'sort_order' => 20 + $i]);
            CmsPost::create(['title' => "Public news {$i}", 'slug' => "public-news-{$i}", 'status' => 'published', 'publish_at' => now()->subMinutes($i), 'body' => '<p>Published content</p>']);
        }
        foreach ([['Foreign', 'published', now(), 'another'], ['Draft', 'draft', now(), 'website-main'], ['Scheduled', 'published', now()->addDay(), 'website-main']] as [$title, $status, $date, $website]) {
            CmsService::create(['website_key' => $website, 'title' => "{$title} service", 'slug' => strtolower($title).'-service', 'status' => $status, 'publish_at' => $date, 'sort_order' => -1]);
            CmsPost::create(['website_key' => $website, 'title' => "{$title} news", 'slug' => strtolower($title).'-news', 'status' => $status, 'publish_at' => $date]);
        }
        $response = $this->get('/vi/ser/'.$service->slug)->assertOk()->assertSee($service->summary)->assertSee($service->content, false)
            ->assertDontSee('Liên kết nhanh');
        foreach (['Foreign', 'Draft', 'Scheduled'] as $hidden) {
            $response->assertDontSee("{$hidden} service")->assertDontSee("{$hidden} news");
        }
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $this->assertSame(10, $xpath->query('//ul[@class="rx13-service-list"]/li')->length);
        $this->assertSame(10, $xpath->query('//ul[@class="rx13-service-news"]/li')->length);
        $this->assertSame(1, $xpath->query('//ul[@class="rx13-service-list"]//a[@aria-current="page"]')->length);
        $this->assertSame('Public news 0', trim($xpath->query('//ul[@class="rx13-service-news"]//h3')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//header[@class="rx13-service-heading"]/h1/following::figure[@class="rx13-service-cover"]')->length);
        $this->assertSame($service->title, $xpath->query('//section[@id="service-contact"]//input[@name="subject"]')->item(0)->getAttribute('value'));
        $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $xpath->query('//section[@id="service-contact"]//form')->item(0)->getAttribute('action'));
        $this->assertSame(0, $xpath->query('//div[@class="rx13-service-gallery"]//img[@src="'.$service->featuredImage->image_url.'"]')->length);
    }

    private function translate(object $model, string $type, string $title, string $slug, string $status = 'published'): void
    {
        $source = ContentTranslation::where('resource_type', $type)->where('resource_id', (string) $model->id)->where('locale', 'vi')->firstOrFail();
        ContentTranslation::updateOrCreate(['website_key' => 'website-main', 'resource_type' => $type, 'resource_id' => (string) $model->id, 'locale' => 'en'], [
            'slug' => $slug, 'payload' => array_replace($source->payload, ['title' => $title, 'slug' => $slug]),
            'translation_status' => $status, 'source_revision' => $source->translation_revision,
            'translation_revision' => $source->translation_revision, 'translation_published_at' => $status === 'published' ? now() : null,
        ]);
        if ($status === 'published') {
            app(LocalizedRouteRegistry::class)->register('en', $type, $model->id,
                $type === 'cms_service' ? FrontendRouteUrl::servicePath($slug) : FrontendRouteUrl::postPath($slug),
                ['is_published' => true]);
        }
    }

    public function test_sidebar_filters_unpublished_translations_before_limiting_and_uses_localized_urls(): void
    {
        $service = $this->generateDemo();
        WebsiteLocale::where('locale', 'en')->update(['is_enabled_for_editing' => true, 'is_published' => true]);
        app(LocaleContext::class)->flush('website-main');
        $this->translate($service, 'cms_service', 'Travel application guidance', 'travel-guidance');
        for ($i = 0; $i < 12; $i++) {
            $draftService = CmsService::create(['title' => "Untranslated service {$i}", 'slug' => "untranslated-service-{$i}", 'status' => 'published', 'sort_order' => -10]);
            $draftPost = CmsPost::create(['title' => "Untranslated news {$i}", 'slug' => "untranslated-news-{$i}", 'status' => 'published', 'publish_at' => now()]);
            $this->translate($draftService, 'cms_service', "Draft service translation {$i}", "draft-service-{$i}", 'draft');
            $this->translate($draftPost, 'cms_post', "Draft news translation {$i}", "draft-news-{$i}", 'draft');
        }
        for ($i = 0; $i < 10; $i++) {
            $translatedService = CmsService::create(['title' => "Source service {$i}", 'slug' => "source-service-{$i}", 'status' => 'published', 'sort_order' => 20 + $i]);
            $post = CmsPost::create(['title' => "Source news {$i}", 'slug' => "source-news-{$i}", 'status' => 'published', 'publish_at' => now()->subMinutes($i + 1)]);
            $this->translate($translatedService, 'cms_service', "English service {$i}", "english-service-{$i}");
            $this->translate($post, 'cms_post', "English news {$i}", "english-news-{$i}");
        }
        $response = $this->get('/en/ser/travel-guidance')->assertOk()->assertSee('Our services')->assertSee('Latest news')
            ->assertDontSee('Untranslated service')->assertDontSee('Draft service translation')->assertDontSee('Draft news translation');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(10, $xpath->query('//ul[@class="rx13-service-list"]/li')->length);
        $this->assertSame(10, $xpath->query('//ul[@class="rx13-service-news"]/li')->length);
        $this->assertSame(1, $xpath->query('//ul[@class="rx13-service-list"]//a[@href="'.FrontendRouteUrl::service('english-service-0', 'en').'"]')->length);
        $this->assertSame(1, $xpath->query('//ul[@class="rx13-service-news"]//a[@href="'.FrontendRouteUrl::post('english-news-9', 'en').'"]')->length);
    }

    public function test_service_form_keeps_invalid_input_then_saves_the_selected_service_subject(): void
    {
        Mail::fake();
        config(['session.serialization' => 'php']);
        $service = $this->generateDemo();
        $url = FrontendRouteUrl::service($service->slug, 'vi');
        $submit = route('site.contact.submit', ['locale' => 'vi']);
        $this->from($url)->post($submit, ['source' => 'contact', 'name' => 'Khách tư vấn', 'email' => 'invalid', 'subject' => 'Chủ đề đã sửa', 'message' => 'ngắn'])
            ->assertSessionHasErrors(['email', 'message']);
        $this->get($url)->assertOk()->assertSee('value="Chủ đề đã sửa"', false)->assertSee('role="alert"', false);
        $this->from($url)->post($submit, ['source' => 'contact', 'name' => 'Khách tư vấn', 'email' => 'travel@example.test', 'subject' => $service->title, 'message' => 'Tôi muốn được tư vấn về hồ sơ cho hành trình sắp tới.'])
            ->assertRedirect($url)->assertSessionHas('contact_status');
        $this->assertDatabaseHas('contact_inquiries', ['website_key' => 'website-main', 'email' => 'travel@example.test', 'subject' => $service->title, 'locale' => 'vi']);
        $this->get($url)->assertOk()->assertSee('role="status"', false);
    }
}
