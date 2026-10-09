<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPageBlock;
use App\Models\LandingPage;
use App\Models\ThemeDemoRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0310HomeBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_repairs_existing_about_and_benefit_images(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0310');
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0310', $provider->defaultPreset());
        ThemeDemoRecord::where('theme_key', 'XD0310')->where('model_type', LandingPage::class)->delete();
        $testimonials = LandingPageBlock::where('block_type', 'bizmax_testimonial_carousel')->firstOrFail();
        $testimonials->data()->where('locale', 'vi')->update([
            'title' => 'Niềm vui bắt đầu từ một khu vườn đáng sống',
            'description' => 'Những phản hồi chân thành sau khi không gian xanh được hoàn thiện và đưa vào sử dụng.',
        ]);
        $types = ['bizmax_about', 'bizmax_benefit_panel'];
        foreach (LandingPageBlock::whereIn('block_type', $types)->with('data')->get() as $block) {
            foreach ($block->data as $data) {
                $content = json_decode($data->content, true);
                $content[$block->block_type === 'bizmax_about' ? 'image_primary' : 'image'] = '/missing-garden.jpg';
                $content['retained_setting'] = 'preserve me';
                $data->update(['content' => json_encode($content)]);
            }
        }
        $generator->generate('XD0310', $provider->defaultPreset());
        foreach (LandingPageBlock::whereIn('block_type', $types)->with('data')->get() as $block) {
            foreach ($block->data as $data) {
                $content = json_decode($data->content, true);
                $field = $block->block_type === 'bizmax_about' ? 'image_primary' : 'image';
                $this->assertFileExists(public_path(ltrim($content[$field], '/')));
                $this->assertSame('preserve me', $content['retained_setting']);
            }
        }
        $response = $this->get('/vi')->assertOk();
        $response->assertDontSee('/missing-garden.jpg');
        $response->assertSee('xd10-quote-author', false);
        $response->assertSee('garden-2.jpg', false);
        if ($preview = getenv('XD0310_BLOCK_PREVIEW')) {
            file_put_contents($preview, $response->getContent());
        }
    }
}
