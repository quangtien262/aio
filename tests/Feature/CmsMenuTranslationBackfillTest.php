<?php

namespace Tests\Feature;

use App\Core\Cms\CmsMenuLocalization;
use App\Core\Cms\CmsMenuTranslationBackfill;
use App\Enums\TranslationStatus;
use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Models\ThemeTranslation;
use App\Support\Localization\TranslationRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsMenuTranslationBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_published_source_and_leaves_an_empty_target_missing(): void
    {
        $menu = $this->menu();
        ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->delete();

        $first = app(CmsMenuTranslationBackfill::class)->run('website-main');
        $second = app(CmsMenuTranslationBackfill::class)->run('website-main');
        $source = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'vi')
            ->sole();

        $this->assertSame(1, $first['source_created']);
        $this->assertSame(1, $second['source_unchanged']);
        $this->assertSame(TranslationStatus::Published, $source->translation_status);
        $this->assertSame($menu->fresh()->items, data_get($source->payload, 'items'));
        $this->assertSame(
            TranslationRevision::fingerprint(['items' => $menu->fresh()->items]),
            $source->translation_revision,
        );
        $this->assertDatabaseMissing('content_translations', [
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
        ]);
    }

    public function test_it_migrates_nested_positional_overrides_to_item_keys_without_deleting_legacy_rows(): void
    {
        $menu = $this->menu();
        $items = $menu->items;
        $legacy = [
            'cms_menu.primary-navigation.0.label' => 'About us',
            'cms_menu.primary-navigation.0.children.0.label' => 'Our team',
            'cms_menu.primary-navigation.1.label' => 'Contact',
        ];

        foreach ($legacy as $key => $value) {
            ThemeTranslation::query()->create([
                'website_key' => 'website-main',
                'theme_key' => 'site-content:website-main',
                'locale' => 'en',
                'group' => 'content',
                'translation_key' => $key,
                'value' => $value,
                'translation_status' => TranslationStatus::Published,
                'is_machine_translated' => false,
            ]);
        }

        $report = app(CmsMenuTranslationBackfill::class)->run('website-main');
        $translation = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->sole();

        $this->assertSame(3, $report['legacy_entries_migrated']);
        $this->assertSame(1, $report['target_created']);
        $this->assertSame(TranslationStatus::Published, $translation->translation_status);
        $this->assertSame(2, data_get($translation->payload, 'items.schema_version'));
        $this->assertSame(
            'About us',
            data_get($translation->payload, 'items.by_key.'.$items[0]['item_key'].'.label'),
        );
        $this->assertSame(
            'Our team',
            data_get(
                $translation->payload,
                'items.by_key.'.$items[0]['children'][0]['item_key'].'.label',
            ),
        );
        $this->assertSame(
            'Contact',
            data_get($translation->payload, 'items.by_key.'.$items[1]['item_key'].'.label'),
        );
        $this->assertSame(
            TranslationRevision::fingerprint(['items' => $items]),
            $translation->source_revision,
        );
        $this->assertDatabaseCount('theme_translations', 3);
    }

    public function test_it_quarantines_a_published_target_that_is_identical_to_source(): void
    {
        $menu = $this->menu();
        $sourcePayload = ['items' => $menu->items];
        ContentTranslation::query()->updateOrCreate(
            [
                'website_key' => 'website-main',
                'resource_type' => 'cms_menu',
                'resource_id' => (string) $menu->id,
                'locale' => 'en',
            ],
            [
                'payload' => $sourcePayload,
                'translation_status' => TranslationStatus::Published,
                'source_revision' => TranslationRevision::fingerprint($sourcePayload),
                'translation_revision' => TranslationRevision::fingerprint($sourcePayload),
                'translation_published_at' => now(),
            ],
        );

        app(CmsMenuTranslationBackfill::class)->run('website-main');
        $translation = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->sole();

        $this->assertSame(
            TranslationStatus::NeedsTranslation,
            $translation->translation_status,
        );
        $this->assertNull($translation->translation_published_at);
        $this->assertSame(2, data_get($translation->payload, 'items.schema_version'));
        $this->assertSame(
            $menu->items[0]['label'],
            data_get(
                $translation->payload,
                'items.by_key.'.$menu->items[0]['item_key'].'.label',
            ),
        );
    }

    public function test_partial_legacy_translation_becomes_a_draft_instead_of_being_auto_published(): void
    {
        $menu = $this->menu();
        ThemeTranslation::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'cms_menu.primary-navigation.0.label',
            'value' => 'About us',
            'translation_status' => TranslationStatus::Published,
            'is_machine_translated' => false,
        ]);

        app(CmsMenuTranslationBackfill::class)->run('website-main');
        $translation = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->sole();

        $this->assertSame(TranslationStatus::Draft, $translation->translation_status);
        $this->assertNull($translation->translation_published_at);
        $this->assertSame(
            '',
            data_get(
                $translation->payload,
                'items.by_key.'.$menu->items[1]['item_key'].'.label',
            ),
        );
    }

    public function test_existing_v2_translation_is_authoritative_over_stale_legacy_values(): void
    {
        $menu = $this->menu();
        $payload = app(CmsMenuLocalization::class)->storagePayload(
            $menu->items,
            [
                'items' => [
                    [
                        'item_key' => $menu->items[0]['item_key'],
                        'label' => 'Current about',
                        'children' => [[
                            'item_key' => $menu->items[0]['children'][0]['item_key'],
                            'label' => 'Current team',
                        ]],
                    ],
                    [
                        'item_key' => $menu->items[1]['item_key'],
                        'label' => 'Current contact',
                    ],
                ],
            ],
        );
        ContentTranslation::query()->create([
            'website_key' => 'website-main',
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
            'payload' => $payload,
            'translation_status' => TranslationStatus::Published,
            'source_revision' => TranslationRevision::fingerprint(['items' => $menu->items]),
            'translation_revision' => TranslationRevision::fingerprint($payload),
            'translation_published_at' => now(),
        ]);
        ThemeTranslation::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'cms_menu.primary-navigation.0.label',
            'value' => 'Stale about',
            'translation_status' => TranslationStatus::Published,
            'is_machine_translated' => false,
        ]);

        app(CmsMenuTranslationBackfill::class)->run('website-main');
        $translation = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->sole();

        $this->assertSame(
            'Current about',
            data_get(
                $translation->payload,
                'items.by_key.'.$menu->items[0]['item_key'].'.label',
            ),
        );
        $this->assertSame(TranslationStatus::Published, $translation->translation_status);
    }

    public function test_strict_audit_rejects_legacy_payload_and_passes_after_backfill(): void
    {
        $menu = $this->menu();
        $targetItems = $menu->items;
        $targetItems[0]['label'] = 'About us';
        $targetItems[0]['children'][0]['label'] = 'Our team';
        $targetItems[1]['label'] = 'Contact';
        ContentTranslation::query()->create([
            'website_key' => 'website-main',
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
            'payload' => ['items' => $targetItems],
            'translation_status' => TranslationStatus::Draft,
            'source_revision' => TranslationRevision::fingerprint(['items' => $menu->items]),
            'translation_revision' => TranslationRevision::fingerprint(['items' => $targetItems]),
        ]);

        $this->artisan('localization:audit', [
            '--website' => 'website-main',
            '--strict' => true,
        ])->assertFailed();

        app(CmsMenuTranslationBackfill::class)->run('website-main');

        $this->artisan('localization:audit', [
            '--website' => 'website-main',
            '--strict' => true,
        ])->assertSuccessful();
    }

    public function test_rerun_never_refreshes_and_republishes_a_stale_v2_translation(): void
    {
        $menu = $this->menu();
        $payload = app(CmsMenuLocalization::class)->storagePayload(
            $menu->items,
            [
                'items' => [
                    [
                        'item_key' => $menu->items[0]['item_key'],
                        'label' => 'About us',
                        'children' => [[
                            'item_key' => $menu->items[0]['children'][0]['item_key'],
                            'label' => 'Our team',
                        ]],
                    ],
                    [
                        'item_key' => $menu->items[1]['item_key'],
                        'label' => 'Contact',
                    ],
                ],
            ],
        );
        ContentTranslation::query()->create([
            'website_key' => 'website-main',
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
            'payload' => $payload,
            'translation_status' => TranslationStatus::Published,
            'source_revision' => 'stale-source-revision',
            'translation_revision' => TranslationRevision::fingerprint($payload),
            'translation_published_at' => now(),
        ]);

        app(CmsMenuTranslationBackfill::class)->run('website-main');
        $translation = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->sole();

        $this->assertSame(TranslationStatus::Outdated, $translation->translation_status);
        $this->assertSame('stale-source-revision', $translation->source_revision);
        $this->assertNull($translation->translation_published_at);
    }

    private function menu(): CmsMenu
    {
        return CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Primary',
            'location' => 'primary-navigation',
            'items' => [
                [
                    'label' => 'Giới thiệu',
                    'url' => '/p/gioi-thieu',
                    'children' => [
                        ['label' => 'Đội ngũ', 'url' => '/p/doi-ngu'],
                    ],
                ],
                ['label' => 'Liên hệ', 'url' => '/p/lien-he'],
            ],
        ]);
    }
}
