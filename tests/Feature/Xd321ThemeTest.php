<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd321ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_xd321_renders_polished_shell_accented_copy_and_scroll_reveal(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD321', 'xd321-cargo-logistics');

        $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
        $profile->forceFill([
            'branding' => array_merge((array) $profile->branding, [
                'company_name' => 'XD321 Cargo Việt Nam',
                'logo_url' => '/storage/branding/xd321-logo.svg',
            ]),
        ])->save();

        $response = $this->get(route('site.home', ['locale' => 'vi']));

        $response->assertOk()
            ->assertSee('Nhanh chóng, an toàn cùng XD321 Cargo', false)
            ->assertSee('Giới thiệu', false)
            ->assertSee('Dịch vụ', false)
            ->assertSee('data-x321-reveal', false)
            ->assertSee('IntersectionObserver', false)
            ->assertSee('foot-header__masthead-inner', false)
            ->assertSee('foot-footer__top', false)
            ->assertSee('x321-process__steps', false)
            ->assertDontSee('Nhanh chong, an toan cung XD321 Cargo', false)
            ->assertDontSee('Gioi thieu', false);

        $this->assertSame(2, substr_count($response->getContent(), 'src="/storage/branding/xd321-logo.svg"'));
    }

    public function test_xd321_shell_styles_are_self_contained_and_responsive(): void
    {
        $styles = file_get_contents(base_path('themes/XD321/views/partials/styles.blade.php'));

        $this->assertStringContainsString('.foot-header__masthead-inner{', $styles);
        $this->assertStringContainsString('.foot-footer__top{display:grid', $styles);
        $this->assertStringContainsString('.x321-js [data-x321-reveal]', $styles);
        $this->assertStringContainsString('@media(max-width:800px)', $styles);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $styles);
    }
}
