<?php

namespace Tests\Feature;

use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeFooterBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_theme_footer_uses_the_configured_website_logo(): void
    {
        $logo = '/theme-demo/complete/logo-foot403.svg';
        $profile = SiteProfile::create(['site_name' => 'Configured brand', 'website_type' => 'corporate', 'active_theme_key' => 'FOOT403', 'branding' => ['logo_url' => $logo, 'company_name' => 'Configured brand']]);
        $failures = [];
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            foreach (['home' => '/vi', 'news' => '/vi/c'] as $mode => $url) {
                $response = $this->get($url)->assertOk();
                $dom = new \DOMDocument;
                @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
                $xpath = new \DOMXPath($dom);
                if ($xpath->query('//footer//img[@src="'.$logo.'"]')->length === 0) {
                    $failures[] = $theme.'-'.$mode;
                }
                if ($folder = getenv('FOOTER_PREVIEW')) {
                    if (! is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }
                    file_put_contents($folder.'/'.$theme.'-'.$mode.'.html', $response->getContent());
                }
            }
        }
        $this->assertSame([], $failures, 'Footers missing configured logo');
        foreach (['AUTO850', 'XD0322', 'SER0101', 'EC906'] as $theme) {
            $replacement = $logo.'?brand=updated';
            $profile->update(['active_theme_key' => $theme, 'branding' => ['logo_url' => $replacement, 'company_name' => 'Updated brand']]);
            $response = $this->get('/vi')->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertGreaterThan(0, $xpath->query('//footer//img[@src="'.$replacement.'"]')->length, $theme);
            $this->assertSame(0, $xpath->query('//footer//img[@src="'.$logo.'"]')->length, $theme);
        }
        $profile->update(['active_theme_key' => 'AUTO850', 'branding' => ['logo_url' => '', 'company_name' => 'Brand without image']]);
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//footer//a[@class="theme-footer-logo"]//img')->length);
        $this->assertStringContainsString('Brand without image', $xpath->query('//footer//a[@class="theme-footer-logo"]')->item(0)->textContent);
    }
}
