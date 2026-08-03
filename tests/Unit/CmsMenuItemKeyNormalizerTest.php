<?php

namespace Tests\Unit;

use App\Core\Cms\CmsMenuItemKeyNormalizer;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class CmsMenuItemKeyNormalizerTest extends TestCase
{
    public function test_it_adds_unique_stable_keys_recursively_without_changing_content(): void
    {
        $normalizer = new CmsMenuItemKeyNormalizer;
        $items = [
            [
                'label' => 'Parent',
                'url' => '/parent',
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
        ];

        $normalized = $normalizer->normalize($items);
        $keys = [
            $normalized[0]['item_key'],
            $normalized[0]['children'][0]['item_key'],
            $normalized[0]['children'][0]['children'][0]['item_key'],
        ];

        $this->assertCount(3, array_unique($keys));
        $this->assertTrue(collect($keys)->every(fn (string $key): bool => Str::isUuid($key)));
        $this->assertSame('Parent', $normalized[0]['label']);
        $this->assertSame('/child', $normalized[0]['children'][0]['url']);
        $this->assertSame($normalized, $normalizer->normalize($normalized));
    }

    public function test_it_preserves_valid_keys_and_replaces_invalid_or_duplicate_keys(): void
    {
        $normalizer = new CmsMenuItemKeyNormalizer;
        $stableKey = (string) Str::uuid();
        $items = [
            ['item_key' => $stableKey, 'label' => 'First'],
            ['item_key' => $stableKey, 'label' => 'Duplicate'],
            ['item_key' => 'temporary-browser-key', 'label' => 'Invalid'],
        ];

        $normalized = $normalizer->normalize($items);

        $this->assertSame($stableKey, $normalized[0]['item_key']);
        $this->assertNotSame($stableKey, $normalized[1]['item_key']);
        $this->assertTrue(Str::isUuid($normalized[1]['item_key']));
        $this->assertTrue(Str::isUuid($normalized[2]['item_key']));
        $this->assertCount(3, array_unique(array_column($normalized, 'item_key')));
    }

    public function test_it_recovers_existing_keys_by_item_identity_when_writer_omits_them(): void
    {
        $normalizer = new CmsMenuItemKeyNormalizer;
        $aboutKey = (string) Str::uuid();
        $contactKey = (string) Str::uuid();
        $existing = [
            ['item_key' => $aboutKey, 'label' => 'Giới thiệu', 'url' => '/about'],
            ['item_key' => $contactKey, 'label' => 'Liên hệ', 'url' => '/contact'],
        ];

        $normalized = $normalizer->normalize([
            ['label' => 'Liên hệ', 'url' => '/contact'],
            ['label' => 'Giới thiệu mới', 'url' => '/about'],
        ], $existing);

        $this->assertSame($contactKey, $normalized[0]['item_key']);
        $this->assertSame($aboutKey, $normalized[1]['item_key']);
    }
}
