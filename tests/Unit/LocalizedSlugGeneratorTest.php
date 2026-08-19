<?php

namespace Tests\Unit;

use App\Support\Localization\LocalizedSlugGenerator;
use PHPUnit\Framework\TestCase;

class LocalizedSlugGeneratorTest extends TestCase
{
    public function test_it_transliterates_non_latin_titles_without_an_external_api(): void
    {
        $generator = new LocalizedSlugGenerator;

        $this->assertSame('gong-si-jian-jie', $generator->normalize('公司简介', 'zh'));
        $this->assertSame('lian-xi-wo-men', $generator->normalize('联系我们', 'zh'));
        $this->assertSame('dong-gyeong-yeohaeng', $generator->normalize('동경 여행', 'ko'));
    }

    public function test_it_generates_stable_unique_suffixes(): void
    {
        $generator = new LocalizedSlugGenerator;
        $used = ['gong-si-jian-jie', 'gong-si-jian-jie-2'];

        $this->assertSame(
            'gong-si-jian-jie-3',
            $generator->unique(
                'gong-si-jian-jie',
                fn (string $slug): bool => in_array($slug, $used, true),
            ),
        );
    }
}
