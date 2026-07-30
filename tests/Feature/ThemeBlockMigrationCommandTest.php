<?php

namespace Tests\Feature;

use App\Models\ThemeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeBlockMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_modify_legacy_rows(): void
    {
        ThemeTranslation::query()->create([
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'theme_section.latest_posts.summary',
            'value' => 'Legacy summary',
        ]);

        $this->artisan('theme-blocks:migrate-legacy', ['--theme' => 'SER0101', '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'theme_section.latest_posts.summary',
            'value' => 'Legacy summary',
        ]);
    }

    public function test_command_migrates_legacy_keys_to_theme_block_namespace(): void
    {
        ThemeTranslation::query()->create([
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'theme_metric.service_metrics.0.label',
            'value' => 'Legacy metric label',
        ]);

        $this->artisan('theme-blocks:migrate-legacy', ['--theme' => 'SER0101'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'theme_metric.service_metrics.0.label',
        ]);

        $this->assertDatabaseHas('theme_translations', [
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'theme_block.ser0101.service_metrics.0.label',
            'value' => 'Legacy metric label',
        ]);
    }
}
