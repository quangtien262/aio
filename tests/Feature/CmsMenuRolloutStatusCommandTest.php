<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\ContentTranslation;
use App\Models\SiteProfile;
use App\Support\Localization\LocalizationRollout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsMenuRolloutStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_command_resolves_the_active_theme_and_reader_decision(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Rollout test',
            'active_theme_key' => 'DN302',
        ]);
        config()->set('localized-content.rollout.reader', 'new');
        config()->set('localized-content.rollout.modules.cms_menu', true);
        config()->set('localized-content.rollout.stages.cms_menu', 'canary');
        config()->set('localized-content.rollout.canaries.cms_menu', [
            'websites' => [],
            'themes' => ['DN302'],
        ]);
        $decision = app(LocalizationRollout::class)->readerDecision(
            'cms_menu',
            'website-main',
            'DN302',
        );

        $this->assertTrue($decision['enabled']);
        $this->assertSame('canary_match', $decision['reason']);

        $this->artisan('localization:menu-rollout-status', [
            '--website' => 'website-main',
            '--json' => true,
        ])
            ->expectsOutputToContain('"theme_key": "DN302"')
            ->assertSuccessful();
    }

    public function test_status_command_fails_closed_for_an_invalid_stage(): void
    {
        config()->set('localized-content.rollout.reader', 'new');
        config()->set('localized-content.rollout.modules.cms_menu', true);
        config()->set('localized-content.rollout.stages.cms_menu', 'invalid');
        $decision = app(LocalizationRollout::class)->readerDecision(
            'cms_menu',
            'website-main',
            'BOOK920',
        );

        $this->assertFalse($decision['enabled']);
        $this->assertSame('invalid_stage', $decision['reason']);

        $this->artisan('localization:menu-rollout-status', [
            '--website' => 'website-main',
            '--theme' => 'BOOK920',
            '--json' => true,
        ])
            ->expectsOutputToContain('"decision_reason": "invalid_stage"')
            ->assertFailed();
    }

    public function test_table_report_keeps_status_rows_from_every_locale(): void
    {
        ContentTranslation::query()->create([
            'website_key' => 'website-main',
            'resource_type' => 'cms_menu',
            'resource_id' => '101',
            'locale' => 'vi',
            'payload' => ['name' => 'Menu nguồn'],
            'translation_status' => TranslationStatus::Published,
        ]);
        ContentTranslation::query()->create([
            'website_key' => 'website-main',
            'resource_type' => 'cms_menu',
            'resource_id' => '101',
            'locale' => 'en',
            'payload' => ['name' => 'Source menu'],
            'translation_status' => TranslationStatus::Ready,
        ]);

        $this->artisan('localization:menu-rollout-status', [
            '--website' => 'website-main',
            '--theme' => 'BOOK920',
        ])
            ->expectsOutputToContain('vi')
            ->expectsOutputToContain('en')
            ->assertSuccessful();
    }
}
