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
}
