<?php

namespace Tests\Unit;

use App\Support\ThemeBlockRegistry;
use Tests\TestCase;

class ThemeBlockRegistryTest extends TestCase
{
    public function test_registry_normalizes_keys_and_returns_theme_specific_entries(): void
    {
        $registry = app(ThemeBlockRegistry::class);

        $this->assertSame('theme_block.ser0100.quote_panel.badge', $registry->contentKey('SER0100', 'quote_panel.badge'));

        $entries = collect($registry->editableEntries('TH0001', 'website-main'));

        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['key'] === 'theme_block.th0001.hero_slide.eyebrow'));
        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['key'] === 'theme_block.th0001.footer.columns.0.title'));
        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['key'] === 'theme_block.th0001.company_footer.address_line_1'));
    }

    public function test_registry_exposes_legacy_map_for_ser0100_only(): void
    {
        $registry = app(ThemeBlockRegistry::class);

        $legacyMap = $registry->legacyKeyMap('SER0100');

        $this->assertSame(
            'theme_block.ser0100.latest_posts.summary',
            $legacyMap['theme_section.latest_posts.summary'] ?? null,
        );
        $this->assertSame(
            'theme_block.ser0100.service_metrics.2.label',
            $legacyMap['theme_metric.service_metrics.2.label'] ?? null,
        );
        $this->assertSame([], $registry->legacyKeyMap('TH0001'));
    }
}
