<?php

namespace Tests\Unit;

use Tests\TestCase;

class MasamiChineseTranslationSeedSnapshotTest extends TestCase
{
    public function test_snapshot_has_the_expected_scope_and_unique_identities(): void
    {
        $snapshot = $this->snapshot();

        $this->assertSame('website-main', $snapshot['metadata']['website_key']);
        $this->assertSame('vi', $snapshot['metadata']['source_locale']);
        $this->assertSame('zh', $snapshot['metadata']['target_locale']);
        $this->assertCount(102, $snapshot['content']);
        $this->assertCount(2, $snapshot['cms_pages']);
        $this->assertCount(1, $snapshot['landing_pages']);
        $this->assertCount(11, $snapshot['landing_blocks']);

        $identities = [];
        foreach ($snapshot['content'] as $entry) {
            $identities[] = $entry['resource_type'].'#'.$entry['resource_id'];
            $this->assertNotSame('', $entry['source_revision']);
            $this->assertNotEmpty($entry['payload']);
        }
        foreach (['cms_pages', 'landing_pages', 'landing_blocks'] as $group) {
            foreach ($snapshot[$group] as $entry) {
                $idKey = match ($group) {
                    'cms_pages' => 'cms_page_id',
                    'landing_pages' => 'landing_page_id',
                    default => 'landing_page_block_id',
                };
                $identities[] = $group.'#'.$entry[$idKey];
                $this->assertNotSame('', $entry['source_revision']);
                $this->assertNotEmpty($entry['payload']);
            }
        }

        $this->assertCount(count($identities), array_unique($identities));
    }

    public function test_snapshot_keeps_menu_structure_and_private_site_settings_out_of_translation(): void
    {
        $content = collect($this->snapshot()['content']);
        $menu = $content->firstWhere('resource_type', 'cms_menu');
        $profile = $content->firstWhere('resource_type', 'site_profile');

        $this->assertSame(2, $menu['payload']['items']['schema_version']);
        $this->assertNotEmpty($menu['payload']['items']['by_key']);
        foreach ($menu['payload']['items']['by_key'] as $item) {
            $this->assertSame(['label'], array_keys($item));
        }

        $branding = $profile['payload']['branding'];
        $this->assertEqualsCanonicalizing([
            'company_name',
            'company_description',
            'slogan',
            'support_location',
            'boc_footer_note',
            'copyright_text',
        ], array_keys($branding));
        $this->assertArrayNotHasKey('support_email', $branding);
        $this->assertArrayNotHasKey('support_hotline', $branding);
        $this->assertArrayNotHasKey('logo_url', $branding);
    }

    public function test_translated_visible_copy_has_chinese_and_no_vietnamese_diacritics(): void
    {
        $visibleCopy = [];

        foreach ($this->snapshotGroups() as $entries) {
            foreach ($entries as $entry) {
                $this->collectVisibleCopy((array) $entry['payload'], $visibleCopy);
            }
        }

        $copy = implode("\n", $visibleCopy);
        $this->assertMatchesRegularExpression('/[\x{3400}-\x{9FFF}]/u', $copy);
        $this->assertDoesNotMatchRegularExpression(
            '/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu',
            $copy,
        );
    }

    public function test_snapshot_does_not_link_chinese_visitors_back_to_vietnamese_routes(): void
    {
        $json = json_encode(
            $this->snapshot(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $this->assertStringNotContainsString('/vi/', $json);
        $this->assertStringContainsString('/zh/', $json);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return json_decode(
            (string) file_get_contents(database_path('seeders/data/masami-zh.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /** @return list<list<array<string, mixed>>> */
    private function snapshotGroups(): array
    {
        $snapshot = $this->snapshot();

        return [
            $snapshot['content'],
            $snapshot['cms_pages'],
            $snapshot['landing_pages'],
            $snapshot['landing_blocks'],
        ];
    }

    /** @param array<string, mixed> $payload @param list<string> $copy */
    private function collectVisibleCopy(array $payload, array &$copy, string $path = ''): void
    {
        foreach ($payload as $key => $value) {
            $currentPath = $path.'/'.$key;
            if (is_array($value)) {
                $this->collectVisibleCopy($value, $copy, $currentPath);

                continue;
            }
            if (! is_string($value) || str_ends_with($currentPath, '/name')) {
                continue;
            }

            $withoutComments = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
            $copy[] = trim(html_entity_decode(strip_tags($withoutComments), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
}
