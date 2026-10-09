<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPageBlock;
use App\Models\LandingPage;
use App\Models\ThemeDemoRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0312HomeBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_repairs_existing_about_and_benefit_images(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0312');
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0312', $provider->defaultPreset());
        ThemeDemoRecord::where('theme_key', 'XD0312')->where('model_type', LandingPage::class)->delete();
        $types = ['bizmax_about', 'bizmax_benefit_panel'];
        foreach (LandingPageBlock::whereIn('block_type', $types)->with('data')->get() as $block) {
            foreach ($block->data as $data) {
                $content = json_decode($data->content, true);
                $content[$block->block_type === 'bizmax_about' ? 'image_primary' : 'image'] = '/missing-garden.jpg';
                $content['retained_setting'] = 'preserve me';
                $data->update(['content' => json_encode($content)]);
            }
        }
        $generator->generate('XD0312', $provider->defaultPreset());
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
        $response->assertSee('logistics-1.jpg', false)->assertSee('logistics-3.jpg', false);
        if ($preview = getenv('XD0312_BLOCK_PREVIEW')) {
            file_put_contents($preview, $response->getContent());
        }
    }
}
