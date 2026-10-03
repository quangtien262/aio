<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0320ContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_uses_projects_and_latest_services_and_only_configured_socials(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->presetsForTheme('XD0320')[0]['key'];
        $generator->generate('XD0320', $preset);
        $generator->generate('XD0320', $preset);
        $this->assertSame(6, CmsService::count());
        $this->assertSame(4, CmsProject::count());
        $category = CmsServiceCategory::where('slug', 'xd0320-dich-vu')->firstOrFail();
        $listing = $this->get(route('site.services.category', ['slug' => $category->slug]))->assertOk();
        $listing->assertSee('xd20-service-page')->assertSee(CmsService::first()->title);
        if ($path = getenv('XD0320_SERVICES_PREVIEW')) {
            file_put_contents($path, $listing->getContent());
        }
        $current = CmsService::firstOrFail();
        $detail = $this->get(route('site.services.show', ['slug' => $current->slug]))->assertOk();
        $detail->assertSee('Dịch vụ mới nhất')->assertDontSee('Liên kết nhanh');
        $detailDom = new \DOMDocument;
        @$detailDom->loadHTML('<?xml encoding="UTF-8">'.$detail->getContent());
        $detailXpath = new \DOMXPath($detailDom);
        $links = $detailXpath->query('//a[@class="xd20-latest-service"]');
        $this->assertSame(5, $links->length);
        foreach ($links as $link) {
            $this->assertStringNotContainsString($current->slug, $link->getAttribute('href'));
            $this->get($link->getAttribute('href'))->assertOk();
        }
        if ($path = getenv('XD0320_DETAIL_PREVIEW')) {
            file_put_contents($path, $detail->getContent());
        }
        $projectsPage = $this->get(route('site.projects.index'))->assertOk();
        if ($path = getenv('XD0320_PROJECTS_PREVIEW')) {
            file_put_contents($path, $projectsPage->getContent());
        }
        $contactPage = $this->get(route('site.contact'))->assertOk();
        if ($path = getenv('XD0320_CONTACT_PREVIEW')) {
            file_put_contents($path, $contactPage->getContent());
        }
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['dich-vu' => 6, 'du-an' => 4] as $id => $count) {
            $cards = $xpath->query('//section[@id="'.$id.'"]//article');
            $this->assertSame($count, $cards->length);
            foreach ($cards as $card) {
                $this->get($xpath->query('.//a', $card)->item(0)->getAttribute('href'))->assertOk();
                $this->assertFileExists(public_path(parse_url($xpath->query('.//img', $card)->item(0)->getAttribute('src'), PHP_URL_PATH)));
            }
        }
        $this->assertStringContainsString(CmsService::latest('id')->first()->title, $xpath->query('//section[@id="dich-vu"]//h3')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//section[@class="foot-footer__social"]')->length);
        $this->assertSame(1, $xpath->query('//section[@class="xd20-footer-company"]//a[contains(@class,"theme-footer-logo")]')->length);
        if ($path = getenv('XD0320_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
        $profile = SiteProfile::first();
        $profile->update(['branding' => array_merge((array) $profile->branding, ['facebook_url' => 'https://facebook.com/example', 'youtube_url' => '', 'instagram_url' => '#'])]);
        $this->get('/vi')->assertOk()->assertSee('https://facebook.com/example')->assertSee('fa-facebook-f')->assertDontSee('fa-instagram');
    }
}
