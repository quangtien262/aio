<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\Admin;
use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsMenuLocalizationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_menu_labels_per_locale_without_copying_structure(): void
    {
        $this->actingAs(
            Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]),
            'admin',
        );
        app(WebsiteLocaleManager::class)->updateLocale(
            'website-main',
            'en',
            ['is_published' => true],
        );
        $menu = CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Primary navigation',
            'location' => 'primary-navigation',
            'items' => [
                [
                    'label' => 'Giới thiệu',
                    'url' => '/p/gioi-thieu',
                    'children' => [
                        [
                            'label' => 'Đội ngũ',
                            'url' => '/p/doi-ngu',
                        ],
                    ],
                ],
            ],
        ]);
        $sourceItems = $menu->items;

        $this->getJson('/admin/api/cms/menus?locale=en')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Primary navigation')
            ->assertJsonPath('data.items.0.items.0.label', 'Giới thiệu')
            ->assertJsonPath('data.items.0._translation_status', 'missing')
            ->assertJsonPath('data.items.0._translation_progress.translated', 0)
            ->assertJsonPath('data.items.0._translation_progress.total', 2);

        $this->getJson("/admin/api/localization/content/cms_menu/{$menu->id}")
            ->assertOk()
            ->assertJsonPath('data.fields', ['items'])
            ->assertJsonPath(
                'data.translation_template.items.0.item_key',
                $sourceItems[0]['item_key'],
            )
            ->assertJsonPath('data.translation_template.items.0.label', '')
            ->assertJsonPath(
                'data.translation_template.items.0._source_label',
                'Giới thiệu',
            );

        $draftItems = $sourceItems;
        $draftItems[0]['label'] = 'About us';
        $draftItems[0]['url'] = '/tampered';
        $draftItems[0]['children'][0]['label'] = '';

        $this->putJson(
            "/admin/api/localization/content/cms_menu/{$menu->id}/en",
            [
                'payload' => ['items' => $draftItems],
                'publish' => false,
            ],
        )->assertOk()
            ->assertJsonPath('data.translation_status', 'draft');

        $storedPayload = ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->value('payload');

        $this->assertSame(2, $storedPayload['items']['schema_version']);
        $this->assertSame(
            ['label' => 'About us'],
            $storedPayload['items']['by_key'][$sourceItems[0]['item_key']],
        );
        $this->assertArrayNotHasKey(
            'url',
            $storedPayload['items']['by_key'][$sourceItems[0]['item_key']],
        );

        $this->putJson(
            "/admin/api/localization/content/cms_menu/{$menu->id}/en",
            [
                'payload' => ['items' => $draftItems],
                'publish' => true,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('payload.items');

        $draftItems[0]['children'][0]['label'] = 'Our team';

        $this->putJson(
            "/admin/api/localization/content/cms_menu/{$menu->id}/en",
            [
                'payload' => ['items' => $draftItems],
                'publish' => true,
            ],
        )->assertOk()
            ->assertJsonPath('data.translation_status', 'published');

        $this->getJson('/admin/api/cms/menus?locale=en')
            ->assertOk()
            ->assertJsonPath('data.items.0.items.0.label', 'About us')
            ->assertJsonPath(
                'data.items.0.items.0.children.0.label',
                'Our team',
            )
            ->assertJsonPath(
                'data.items.0.items.0.url',
                '/p/gioi-thieu',
            )
            ->assertJsonPath('data.items.0._translation_status', 'published')
            ->assertJsonPath('data.items.0._translation_progress.translated', 2)
            ->assertJsonPath('data.items.0._translation_progress.total', 2);

        $localized = app(LocalizedContentRepository::class)->localize(
            $menu->fresh(),
            'cms_menu',
            'en',
            'website-main',
        );

        $this->assertSame('About us', $localized->items[0]['label']);
        $this->assertSame('/p/gioi-thieu', $localized->items[0]['url']);

        $sourceItems[0]['label'] = 'Giới thiệu cập nhật';
        $menu->update(['items' => $sourceItems]);

        $this->assertDatabaseHas('content_translations', [
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
            'translation_status' => TranslationStatus::Outdated->value,
        ]);
    }
}
