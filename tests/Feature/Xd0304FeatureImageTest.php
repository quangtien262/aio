<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0304FeatureImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_creates_local_feature_images(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0304', 'xd0304-logistics');
        $block = LandingPageBlock::where('block_type', 'logistics_feature_panel')->firstOrFail();
        $rows = $block->data()->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $content = json_decode($row->content, true);
            $this->assertSame('/theme-demo/xd-shared/logistics-1.jpg', $content['image']);
            $this->assertFileExists(public_path($content['image']));
            $content['image'] = '';
            $row->update(['content' => json_encode($content)]);
        }

        $generator->generate('XD0304', 'xd0304-logistics');
        $block = LandingPageBlock::where('block_type', 'logistics_feature_panel')->firstOrFail();
        $rows = $block->data()->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('/theme-demo/xd-shared/logistics-1.jpg', json_decode($row->content, true)['image']);
        }
    }

    public function test_existing_empty_block_uses_local_fallback_image(): void
    {
        $html = view('theme-xd0304::partials.blocks.logistics_feature_panel', [
            'anchor' => 'gioi-thieu', 'content' => ['image' => ''], 'data' => ['title' => 'Logistics'],
        ])->render();

        $this->assertStringContainsString('src="/theme-demo/xd-shared/logistics-1.jpg"', $html);
        $this->assertStringNotContainsString('images.unsplash.com', $html);
    }
}
