<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\ThemeBlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Ser0100RemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_ser0100_source_registry_and_runtime_data_are_removed(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('themes/SER0100'));
        $this->assertDirectoryDoesNotExist(public_path('theme-previews/SER0100'));
        $this->assertFalse(
            app(ThemeRegistry::class)->all()->contains(
                fn (array $theme): bool => strcasecmp((string) $theme['key'], 'SER0100') === 0,
            ),
        );
        $this->assertFalse(app(LandingPageBuilder::class)->supportsTheme('SER0100'));
        $this->assertSame([], app(ThemeBlockRegistry::class)->editableEntries('SER0100', 'website-main'));

        $this->assertSame(0, DB::table('theme_installations')->where('key', 'SER0100')->count());
        $this->assertSame(0, DB::table('site_theme_profiles')->where('theme_key', 'SER0100')->count());
        $this->assertSame(0, DB::table('landing_pages')->where('theme_key', 'SER0100')->count());
        $this->assertSame(0, DB::table('landing_page_blocks')->where('theme_key', 'SER0100')->count());
        $this->assertSame(0, DB::table('site_banners')->where('theme_key', 'SER0100')->count());
        $this->assertSame(0, DB::table('theme_demo_records')->where('theme_key', 'SER0100')->count());
        $this->assertSame(0, DB::table('site_profiles')->where('active_theme_key', 'SER0100')->count());
        $this->assertSame(0, DB::table('sites')->where('theme_key', 'SER0100')->count());
        $this->assertSame(
            0,
            DB::table('theme_translations')
                ->where('theme_key', 'SER0100')
                ->orWhere('translation_key', 'like', 'theme_block.ser0100.%')
                ->count(),
        );
    }
}
