<?php

namespace Tests\Feature;

use App\Core\Cms\CmsMenuLinkIdentityBackfill;
use App\Core\Cms\CmsMenuLocationRegistry;
use App\Http\Controllers\Admin\Api\Cms\MenuManagementController;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Support\Localization\TranslationRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsMenuItemKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_assigns_keys_at_every_depth_and_preserves_them_on_update(): void
    {
        $menu = CmsMenu::query()->create([
            'name' => 'Primary',
            'location' => 'primary',
            'items' => [
                [
                    'label' => 'Parent',
                    'children' => [
                        [
                            'label' => 'Child',
                            'children' => [
                                ['label' => 'Grandchild'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $originalItems = $menu->items;
        $originalKeys = $this->flattenKeys($originalItems);

        $originalItems[0]['label'] = 'Parent updated';
        $originalItems[0]['children'][] = ['label' => 'New child'];
        $menu->update(['items' => $originalItems]);
        $updatedKeys = $this->flattenKeys($menu->fresh()->items);

        $this->assertCount(3, $originalKeys);
        $this->assertCount(4, $updatedKeys);
        $this->assertSame($originalKeys, array_slice($updatedKeys, 0, 3));
        $this->assertTrue(Str::isUuid($updatedKeys[3]));
        $this->assertCount(4, array_unique($updatedKeys));
    }

    public function test_admin_update_retains_keys_for_arbitrarily_nested_items(): void
    {
        $menu = CmsMenu::query()->create([
            'name' => 'Primary',
            'location' => 'primary',
            'items' => [
                [
                    'label' => 'Parent',
                    'url' => '/',
                    'children' => [
                        [
                            'label' => 'Child',
                            'url' => '/child',
                            'children' => [
                                ['label' => 'Grandchild', 'url' => '/grandchild'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $items = $menu->items;
        $keysBefore = $this->flattenKeys($items);
        $items[0]['children'][0]['children'][0]['label'] = 'Grandchild updated';
        $request = Request::create('/admin/api/cms/menus/'.$menu->id, 'PUT', [
            'name' => $menu->name,
            'location' => $menu->location,
            'items' => $items,
        ]);

        app(MenuManagementController::class)->update(
            $request,
            app(CmsMenuLocationRegistry::class),
            $menu->id,
        );

        $fresh = $menu->fresh();
        $this->assertSame($keysBefore, $this->flattenKeys($fresh->items));
        $this->assertSame(
            'Grandchild updated',
            $fresh->items[0]['children'][0]['children'][0]['label'],
        );
    }

    public function test_server_side_demo_style_update_without_keys_keeps_identity_after_reorder(): void
    {
        $menu = CmsMenu::query()->create([
            'name' => 'Primary',
            'location' => 'primary',
            'items' => [
                ['label' => 'About', 'url' => '/about'],
                ['label' => 'Contact', 'url' => '/contact'],
            ],
        ]);
        $aboutKey = $menu->items[0]['item_key'];
        $contactKey = $menu->items[1]['item_key'];

        $menu->update([
            'items' => [
                ['label' => 'Contact', 'url' => '/contact'],
                ['label' => 'About us', 'url' => '/about'],
            ],
        ]);

        $this->assertSame($contactKey, $menu->fresh()->items[0]['item_key']);
        $this->assertSame($aboutKey, $menu->fresh()->items[1]['item_key']);
    }

    public function test_admin_persists_explicit_resource_identity_for_internal_links(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giá»›i thiá»‡u',
            'slug' => 'gioi-thieu',
            'status' => 'published',
        ]);
        $request = Request::create('/admin/api/cms/menus', 'POST', [
            'name' => 'Primary',
            'location' => 'primary',
            'items' => [[
                'label' => 'Giá»›i thiá»‡u',
                'url' => '/p/gioi-thieu',
                'link_type' => 'page',
                'link_value' => (string) $page->id,
            ]],
        ]);

        app(MenuManagementController::class)->store(
            $request,
            app(CmsMenuLocationRegistry::class),
        );
        $item = CmsMenu::query()->latest('id')->firstOrFail()->items[0];

        $this->assertSame('page', $item['link_type']);
        $this->assertSame((string) $page->id, $item['link_value']);
        $this->assertSame('cms_page', $item['resource_type']);
        $this->assertSame((string) $page->id, $item['resource_id']);
    }

    public function test_link_identity_backfill_is_idempotent_for_legacy_url_only_menu(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giá»›i thiá»‡u',
            'slug' => 'gioi-thieu',
            'status' => 'published',
        ]);
        $menu = CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Legacy',
            'location' => 'primary',
            'items' => [[
                'label' => 'Giá»›i thiá»‡u',
                'url' => '/p/gioi-thieu',
            ]],
        ]);

        $first = app(CmsMenuLinkIdentityBackfill::class)->run('website-main');
        $item = $menu->fresh()->items[0];
        $second = app(CmsMenuLinkIdentityBackfill::class)->run('website-main');

        $this->assertSame(1, $first['menus_updated']);
        $this->assertSame(1, $first['items_identified']);
        $this->assertSame('page', $item['link_type']);
        $this->assertSame((string) $page->id, $item['link_value']);
        $this->assertSame('cms_page', $item['resource_type']);
        $this->assertSame((string) $page->id, $item['resource_id']);
        $this->assertSame(0, $second['menus_updated']);
        $this->assertDatabaseHas('content_translations', [
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'vi',
            'translation_status' => 'published',
        ]);
    }

    public function test_backfill_aligns_source_and_target_translations_without_changing_content(): void
    {
        $menu = CmsMenu::withoutEvents(fn (): CmsMenu => CmsMenu::query()->create([
            'name' => 'Legacy menu',
            'location' => 'primary',
            'items' => [
                [
                    'label' => 'Giới thiệu',
                    'url' => '/p/gioi-thieu',
                    'children' => [
                        ['label' => 'Đội ngũ', 'url' => '/p/doi-ngu'],
                    ],
                ],
            ],
        ]));
        $sourcePayload = [
            'name' => 'Legacy menu',
            'items' => $menu->items,
        ];
        $targetPayload = [
            'name' => 'Legacy menu',
            'items' => [
                [
                    'label' => 'About us',
                    'url' => '/p/gioi-thieu',
                    'children' => [
                        ['label' => 'Our team', 'url' => '/p/doi-ngu'],
                    ],
                ],
            ],
        ];

        foreach (['vi' => $sourcePayload, 'en' => $targetPayload] as $locale => $payload) {
            DB::table('content_translations')->insert([
                'website_key' => 'website-main',
                'resource_type' => 'cms_menu',
                'resource_id' => (string) $menu->id,
                'locale' => $locale,
                'slug' => null,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'translation_status' => 'published',
                'source_revision' => TranslationRevision::fingerprint($sourcePayload),
                'translation_revision' => TranslationRevision::fingerprint($payload),
                'is_machine_translated' => false,
                'translation_meta' => null,
                'translated_at' => now(),
                'reviewed_at' => now(),
                'translation_published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path(
            'migrations/2026_07_31_000001_add_stable_item_keys_to_cms_menus.php',
        );
        $migration->up();

        $source = json_decode(
            (string) DB::table('content_translations')
                ->where('resource_type', 'cms_menu')
                ->where('resource_id', (string) $menu->id)
                ->where('locale', 'vi')
                ->value('payload'),
            true,
        );
        $target = json_decode(
            (string) DB::table('content_translations')
                ->where('resource_type', 'cms_menu')
                ->where('resource_id', (string) $menu->id)
                ->where('locale', 'en')
                ->value('payload'),
            true,
        );
        $master = $menu->fresh()->items;

        $this->assertSame('Giới thiệu', $master[0]['label']);
        $this->assertSame('/p/gioi-thieu', $master[0]['url']);
        $this->assertSame('About us', $target['items'][0]['label']);
        $this->assertSame('Our team', $target['items'][0]['children'][0]['label']);
        $this->assertSame($master[0]['item_key'], $source['items'][0]['item_key']);
        $this->assertSame($master[0]['item_key'], $target['items'][0]['item_key']);
        $this->assertSame(
            $master[0]['children'][0]['item_key'],
            $target['items'][0]['children'][0]['item_key'],
        );
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private function flattenKeys(array $items): array
    {
        $keys = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $keys[] = (string) ($item['item_key'] ?? '');

            if (is_array($item['children'] ?? null)) {
                $keys = [...$keys, ...$this->flattenKeys($item['children'])];
            }
        }

        return $keys;
    }
}
