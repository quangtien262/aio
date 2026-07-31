<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Th0050ThemeBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_th0050_preserves_and_renders_database_branding_in_header_and_footer(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('TH0050');

        $this->assertNotNull($provider);
        $provider->generate($provider->defaultPreset());

        $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
        $logoUrl = '/storage/branding/th0050-custom-logo.svg';
        $hotline = '028 7777 5050';
        $email = 'wellness@example.test';

        $profile->update([
            'branding' => array_merge((array) $profile->branding, [
                'logo_url' => $logoUrl,
                'support_hotline' => $hotline,
                'support_email' => $email,
            ]),
        ]);

        $provider->generate($provider->defaultPreset());

        $branding = (array) SiteProfile::query()
            ->where('website_key', 'website-main')
            ->firstOrFail()
            ->branding;

        $this->assertSame($logoUrl, $branding['logo_url']);
        $this->assertSame($hotline, $branding['support_hotline']);
        $this->assertSame($email, $branding['support_email']);

        $response = $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('src="'.$logoUrl.'"', false)
            ->assertSee($hotline)
            ->assertSee($email);

        $this->assertSame(2, substr_count($response->getContent(), 'src="'.$logoUrl.'"'));
        $response
            ->assertDontSee('0399162342')
            ->assertDontSee('support@htrvietnam.vn');
    }
}
