<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use App\Models\SiteThemeProfile;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeBrandingContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_theme_renders_its_database_logo_and_contacts(): void
    {
        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Branding contract',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DN202',
            'branding' => [],
        ]);
        $failures = [];

        foreach (app(ThemeRegistry::class)->all() as $theme) {
            $themeKey = (string) $theme['key'];
            $slug = strtolower($themeKey);
            $expected = [
                'logo_url' => "https://example.test/branding/{$slug}.png",
                'support_hotline' => "0900-{$slug}-hotline",
                'support_email' => "{$slug}@example.test",
                'support_location' => "Address sentinel {$themeKey}",
            ];

            SiteThemeProfile::query()->updateOrCreate(
                ['website_key' => 'website-main', 'theme_key' => strtoupper($themeKey)],
                ['branding' => $expected],
            );
            $profile->forceFill(['active_theme_key' => $themeKey])->save();

            $response = $this->get('/vi');

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$themeKey}: HTTP {$response->getStatusCode()}";

                continue;
            }

            [$header, $footer] = $this->headerAndFooter($response->getContent());

            if (! str_contains($header, $expected['logo_url'])) {
                $failures[] = "{$themeKey}: header logo";
            }

            foreach (['support_hotline', 'support_email', 'support_location'] as $field) {
                if (! str_contains($footer, $expected[$field])) {
                    $failures[] = "{$themeKey}: footer {$field}";
                }
            }
        }

        $this->assertSame([], $failures, "Theme branding contract failures:\n".implode("\n", $failures));
    }

    public function test_every_registered_theme_leaves_missing_database_branding_blank(): void
    {
        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Empty branding contract',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DN202',
            'branding' => [],
        ]);
        $failures = [];

        foreach (app(ThemeRegistry::class)->all() as $theme) {
            $themeKey = (string) $theme['key'];

            SiteThemeProfile::query()->updateOrCreate(
                ['website_key' => 'website-main', 'theme_key' => strtoupper($themeKey)],
                ['branding' => []],
            );
            $profile->forceFill(['active_theme_key' => $themeKey])->save();

            $response = $this->get('/vi');

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$themeKey}: HTTP {$response->getStatusCode()}";

                continue;
            }

            [$header, $footer] = $this->headerAndFooter($response->getContent());
            $shell = $header.$footer;
            $visibleText = html_entity_decode(strip_tags($shell), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($this->containsBrandImage($shell)) {
                $failures[] = "{$themeKey}: fallback logo";
            }

            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $visibleText, $emailMatch) === 1) {
                $failures[] = "{$themeKey}: fallback email {$emailMatch[0]}";
            }

            if (preg_match('/(?:0\d|1800|1900)[\d .-]{6,}\d/', $visibleText, $hotlineMatch) === 1) {
                $failures[] = "{$themeKey}: fallback hotline {$hotlineMatch[0]}";
            }

            if (preg_match('/Đội Cấn|Lữ Gia|Nguyễn (?:Khuyến|Đình Chiểu)|An Thượng|Xuân Thủy|TP\.?\s*(?:HCM|Hồ Chí Minh)/iu', $visibleText, $addressMatch) === 1) {
                $failures[] = "{$themeKey}: fallback address {$addressMatch[0]}";
            }
        }

        $this->assertSame([], $failures, "Empty branding contract failures:\n".implode("\n", $failures));
    }

    /**
     * @return array{string, string}
     */
    private function headerAndFooter(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $collect = function (string $tag) use ($document): string {
            $html = '';

            foreach ($document->getElementsByTagName($tag) as $node) {
                $html .= $document->saveHTML($node);
            }

            return $html;
        };

        return [$collect('header'), $collect('footer')];
    }

    private function containsBrandImage(string $html): bool
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('a') as $link) {
            $class = strtolower($link->getAttribute('class'));

            if (
                (str_contains($class, 'logo') || str_contains($class, 'brand'))
                && $link->getElementsByTagName('img')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }
}
