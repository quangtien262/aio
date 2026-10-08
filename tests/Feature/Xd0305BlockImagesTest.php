<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0305BlockImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_and_reseed_use_existing_local_images_for_both_blocks(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        foreach (range(1, 2) as $run) {
            $generator->generate('XD0305', 'xd0305-business-consulting');
            foreach (['bizmax_about' => ['image_primary', 'business-2.jpg'], 'bizmax_benefit_panel' => ['image', 'business-3.jpg']] as $type => [$field, $filename]) {
                $block = LandingPageBlock::where('block_type', $type)->firstOrFail();
                foreach ($block->data()->get() as $row) {
                    $content = json_decode($row->content, true);
                    $this->assertSame('/theme-demo/xd-shared/'.$filename, $content[$field]);
                    $this->assertFileExists(public_path($content[$field]));
                    $content[$field] = '';
                    $row->update(['content' => json_encode($content)]);
                }
            }
        }
    }

    public function test_old_empty_image_data_renders_local_fallbacks(): void
    {
        foreach (['bizmax_about' => ['image_primary', 'business-2.jpg'], 'bizmax_benefit_panel' => ['image', 'business-3.jpg']] as $type => [$field, $filename]) {
            $html = view('theme-xd0305::partials.blocks.'.$type, ['content' => [$field => ''], 'data' => ['title' => 'Business'], 'anchor' => $type])->render();
            $this->assertStringContainsString('src="/theme-demo/xd-shared/'.$filename.'"', $html);
            $this->assertStringNotContainsString('images.unsplash.com', $html);
        }
    }
}
