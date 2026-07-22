<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAdminEditorCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_registered_landing_themes_render_quick_edit_controls_in_admin_mode(): void
    {
        $builder = app(LandingPageBuilder::class);
        $themeKeys = app(ThemeRegistry::class)->all()
            ->pluck('key')
            ->filter(fn (?string $key): bool => $builder->supportsTheme($key))
            ->when(
                filled(env('THEME_AUDIT_KEY')),
                fn ($keys) => $keys->filter(fn (string $key): bool => $key === strtoupper((string) env('THEME_AUDIT_KEY'))),
            )
            ->values();

        if (filled(env('THEME_AUDIT_KEY'))) {
            $this->withoutExceptionHandling();
        }

        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Landing editor audit',
            'website_type' => 'service',
            'active_theme_key' => $themeKeys->first(),
            'is_setup_completed' => true,
            'completed_steps' => [],
            'branding' => [],
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $failures = [];

        foreach ($themeKeys as $themeKey) {
            $profile->forceFill(['active_theme_key' => $themeKey])->save();
            $builder->seedHome('website-main', $themeKey);

            $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']));
            $content = $response->getContent();

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$themeKey}: HTTP {$response->getStatusCode()}";
                continue;
            }
            if (! str_contains($content, 'data-xd-edit-block')) {
                $failures[] = "{$themeKey}: thiếu nút Sửa khối";
            }
            if (! str_contains($content, 'data-xd-editor')) {
                $failures[] = "{$themeKey}: thiếu modal editor";
            }
            if (! str_contains($content, 'updateUrlTemplate')) {
                $failures[] = "{$themeKey}: thiếu script mở/lưu editor";
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }
}
