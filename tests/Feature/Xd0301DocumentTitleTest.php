<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPage;
use App\Models\LandingPageData;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0301DocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_database_site_name_instead_of_generated_landing_title(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0301', 'construction-materials');

        SiteProfile::query()->firstOrFail()->forceFill([
            'site_name' => 'Công ty Xây dựng từ Database',
            'active_theme_key' => 'XD0301',
        ])->save();

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('<title>Công ty Xây dựng từ Database</title>', false)
            ->assertDontSee('<title>XD0301 Landing</title>', false);
    }

    public function test_custom_landing_meta_title_keeps_seo_precedence(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0301', 'construction-materials');

        SiteProfile::query()->firstOrFail()->forceFill([
            'site_name' => 'Tên website trong Database',
            'active_theme_key' => 'XD0301',
        ])->save();
        $landingPageId = LandingPage::query()
            ->where('theme_key', 'XD0301')
            ->where('is_home', true)
            ->valueOrFail('id');
        LandingPageData::query()
            ->where('landing_page_id', $landingPageId)
            ->where('locale', 'vi')
            ->update(['meta_title' => 'SEO Title tùy chỉnh trong Database']);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('<title>SEO Title tùy chỉnh trong Database</title>', false);
    }
}
