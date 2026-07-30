<?php

namespace Tests\Unit;

use App\Support\ThemeBlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeBlockRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_normalizes_keys_and_returns_theme_specific_entries(): void
    {
        $registry = app(ThemeBlockRegistry::class);

        $this->assertSame('theme_block.ser0101.quote_panel.badge', $registry->contentKey('SER0101', 'quote_panel.badge'));

        $entries = collect($registry->editableEntries('SER0101', 'website-main'));

        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['key'] === 'theme_block.ser0101.quote_panel.badge'));
        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['key'] === 'theme_block.ser0101.service_metrics.0.value'));
        $this->assertTrue($entries->contains(fn (array $entry): bool => $entry['key'] === 'theme_block.ser0101.latest_posts.summary'));
    }

    public function test_registry_exposes_legacy_map_for_ser0101_only(): void
    {
        $registry = app(ThemeBlockRegistry::class);

        $legacyMap = $registry->legacyKeyMap('SER0101');

        $this->assertSame(
            'theme_block.ser0101.latest_posts.summary',
            $legacyMap['theme_section.latest_posts.summary'] ?? null,
        );
        $this->assertSame(
            'theme_block.ser0101.service_metrics.2.label',
            $legacyMap['theme_metric.service_metrics.2.label'] ?? null,
        );
        $this->assertSame([], $registry->legacyKeyMap('UNKNOWN'));
    }
}
