<?php

namespace Tests\Unit;

use App\Support\Localization\LocalizationRollout;
use Tests\TestCase;

class LocalizationRolloutTest extends TestCase
{
    public function test_canary_stage_only_enables_registered_websites_or_themes(): void
    {
        $this->configureMenuRollout('canary');
        config()->set('localized-content.rollout.canaries.cms_menu', [
            'websites' => ['website-canary'],
            'themes' => ['BOOK920', 'DN302', 'BDS701'],
        ]);

        $rollout = app(LocalizationRollout::class);

        $this->assertTrue($rollout->usesNewReader(
            'cms_menu',
            'website-main',
            'book920',
        ));
        $this->assertTrue($rollout->usesNewReader(
            'cms_menu',
            'website-canary',
            'XD0301',
        ));
        $this->assertFalse($rollout->usesNewReader(
            'cms_menu',
            'website-main',
            'XD0301',
        ));
    }

    public function test_website_override_is_the_emergency_switch_and_wins_over_theme(): void
    {
        $this->configureMenuRollout('all');
        config()->set('localized-content.rollout.overrides.cms_menu', [
            'websites' => ['website-main' => false],
            'themes' => ['BOOK920' => true],
        ]);

        $decision = app(LocalizationRollout::class)->readerDecision(
            'cms_menu',
            'website-main',
            'BOOK920',
        );

        $this->assertFalse($decision['enabled']);
        $this->assertSame('website_override', $decision['reason']);
    }

    public function test_global_legacy_and_invalid_stages_fail_closed(): void
    {
        $this->configureMenuRollout('unexpected');
        $rollout = app(LocalizationRollout::class);

        $decision = $rollout->readerDecision(
            'cms_menu',
            'website-main',
            'BOOK920',
        );
        $this->assertFalse($decision['enabled']);
        $this->assertSame('invalid_stage', $decision['reason']);

        config()->set('localized-content.rollout.stages.cms_menu', 'all');
        config()->set('localized-content.rollout.reader', 'legacy');

        $this->assertFalse($rollout->usesNewReader(
            'cms_menu',
            'website-main',
            'BOOK920',
        ));
    }

    private function configureMenuRollout(string $stage): void
    {
        config()->set('localized-content.rollout.reader', 'new');
        config()->set('localized-content.rollout.modules.cms_menu', true);
        config()->set('localized-content.rollout.stages.cms_menu', $stage);
        config()->set('localized-content.rollout.websites', []);
        config()->set('localized-content.rollout.themes', []);
        config()->set('localized-content.rollout.overrides.cms_menu', [
            'websites' => [],
            'themes' => [],
        ]);
        config()->set('localized-content.rollout.canaries.cms_menu', [
            'websites' => [],
            'themes' => [],
        ]);
    }
}
