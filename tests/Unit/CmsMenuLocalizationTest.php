<?php

namespace Tests\Unit;

use App\Core\Cms\CmsMenuLocalization;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsMenuLocalizationTest extends TestCase
{
    public function test_it_stores_only_labels_by_stable_key_and_rebuilds_source_structure(): void
    {
        $service = app(CmsMenuLocalization::class);
        $source = [
            [
                'item_key' => 'parent-key',
                'label' => 'Giới thiệu',
                'url' => '/p/gioi-thieu',
                'target' => '_self',
                'children' => [
                    [
                        'item_key' => 'child-key',
                        'label' => 'Đội ngũ',
                        'url' => '/p/doi-ngu',
                    ],
                ],
            ],
        ];
        $payload = $service->storagePayload($source, [
            'items' => [
                [
                    'item_key' => 'parent-key',
                    'label' => 'About us',
                    'url' => '/tampered',
                    'children' => [
                        [
                            'item_key' => 'child-key',
                            'label' => 'Our team',
                            'url' => '/tampered-child',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'items' => [
                'schema_version' => 2,
                'by_key' => [
                    'parent-key' => ['label' => 'About us'],
                    'child-key' => ['label' => 'Our team'],
                ],
            ],
        ], $payload);

        $localized = $service->localizedItems($source, $payload);

        $this->assertSame('About us', $localized[0]['label']);
        $this->assertSame('/p/gioi-thieu', $localized[0]['url']);
        $this->assertSame('Our team', $localized[0]['children'][0]['label']);
        $this->assertSame('/p/doi-ngu', $localized[0]['children'][0]['url']);

        $blankPayload = $payload;
        $blankPayload['items']['by_key']['parent-key']['label'] = '';
        $this->assertSame(
            $source[0]['label'],
            $service->localizedItems($source, $blankPayload)[0]['label'],
        );
    }

    public function test_editor_supports_legacy_tree_and_publish_requires_every_label(): void
    {
        $service = app(CmsMenuLocalization::class);
        $source = [
            ['item_key' => 'one', 'label' => 'Một', 'url' => '/mot'],
            ['item_key' => 'two', 'label' => 'Hai', 'url' => '/hai'],
        ];
        $legacyPayload = [
            'items' => [
                ['item_key' => 'one', 'label' => 'One'],
                ['item_key' => 'two', 'label' => ''],
            ],
        ];
        $editable = $service->editableItems($source, $legacyPayload);

        $this->assertSame('One', $editable[0]['label']);
        $this->assertSame('Một', $editable[0]['_source_label']);
        $this->assertSame('', $editable[1]['label']);
        $this->assertSame([
            'translated' => 1,
            'total' => 2,
            'percentage' => 50,
            'complete' => false,
        ], $service->progress($source, $legacyPayload));

        $this->expectException(ValidationException::class);
        $service->assertPublishable($source, $legacyPayload);
    }
}
