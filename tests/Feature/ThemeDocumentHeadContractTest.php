<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeDocumentHeadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_theme_layout_uses_the_shared_storefront_head_component(): void
    {
        $failures = [];

        foreach (app(ThemeRegistry::class)->all() as $theme) {
            $themeKey = (string) $theme['key'];
            $layoutPath = base_path("themes/{$themeKey}/views/layout.blade.php");

            if (! File::exists($layoutPath)) {
                continue;
            }

            $layout = File::get($layoutPath);

            if (! str_contains($layout, '<x-storefront-head')) {
                $failures[] = "{$themeKey}: missing shared component";
            }

            if (preg_match('/<head(?:\s|>)/i', $layout) === 1) {
                $failures[] = "{$themeKey}: owns a raw head element";
            }

            if (preg_match('/<title(?:\s|>)/i', $layout) === 1) {
                $failures[] = "{$themeKey}: owns a raw title element";
            }
        }

        $this->assertSame([], $failures, "Theme document head contract failures:\n".implode("\n", $failures));
    }

    public function test_storefront_fallback_documents_use_the_shared_head_component(): void
    {
        foreach ([
            resource_path('views/site.blade.php'),
            resource_path('views/site-cms.blade.php'),
            resource_path('views/partials/configurable-landing-document.blade.php'),
        ] as $documentPath) {
            $document = File::get($documentPath);

            $this->assertStringContainsString('<x-storefront-head', $document, $documentPath);
            $this->assertDoesNotMatchRegularExpression('/<head(?:\s|>)/i', $document, $documentPath);
            $this->assertDoesNotMatchRegularExpression('/<title(?:\s|>)/i', $document, $documentPath);
        }
    }

    public function test_shared_head_renders_database_document_metadata(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Website Sentinel',
            'description' => 'Mô tả website từ database.',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'NT504',
            'branding' => [
                'company_name' => 'Company Sentinel',
                'logo_url' => 'https://example.test/logo.png',
                'favicon_url' => 'https://example.test/favicon.ico',
            ],
        ]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('<title>Website Sentinel</title>', false)
            ->assertSee('<meta name="description" content="Mô tả website từ database.">', false)
            ->assertSee('<link rel="icon" href="https://example.test/favicon.ico">', false)
            ->assertSee('<meta property="og:title" content="Website Sentinel">', false)
            ->assertSee('<meta property="og:image" content="https://example.test/logo.png">', false);
    }
}
