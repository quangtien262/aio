<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPageBlock;
use App\Models\SiteThemeProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0309HomeBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_repairs_existing_images_and_theme_branding(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0309');
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0309', $provider->defaultPreset());
        SiteThemeProfile::create(['theme_key' => 'XD0309', 'branding' => ['company_description' => 'GiÃ¡ÂºÂ£i phÃƒÂ¡p', 'support_hotline' => '0399162342']]);
        foreach (LandingPageBlock::whereIn('block_type', ['bizmax_about', 'bizmax_benefit_panel'])->with('data')->get() as $block) {
            foreach ($block->data as $data) {
                $data->update(['content' => json_encode(['image_primary' => '/missing.jpg', 'image' => '/missing.jpg'])]);
            }
        }
        $generator->generate('XD0309', $provider->defaultPreset());
        $branding = SiteThemeProfile::where('theme_key', 'XD0309')->firstOrFail()->branding;
        $this->assertSame('Giải pháp an toàn công nghiệp với quy trình minh bạch và hỗ trợ tận tâm.', $branding['company_description']);
        $this->assertSame('0399162342', $branding['support_hotline']);
        foreach (LandingPageBlock::whereIn('block_type', ['bizmax_about', 'bizmax_benefit_panel', 'bizmax_contact'])->with('data')->get() as $block) {
            foreach ($block->data as $data) {
                $content = json_decode($data->content, true);
                $field = match ($block->block_type) {
                    'bizmax_about' => 'image_primary',
                    'bizmax_benefit_panel' => 'image',
                    'bizmax_contact' => 'background_image',
                };
                $this->assertFileExists(public_path(ltrim($content[$field], '/')));
            }
        }
        $response = $this->get('/vi')->assertOk();
        $response->assertSee($branding['company_description']);
        $response->assertDontSee('GiÃ¡');
        $response->assertDontSee('/missing.jpg');
        $response->assertSee('data-xd9-news-track', false);
        $response->assertSee('xd9-contact-background', false);
        if ($preview = getenv('XD0309_BLOCK_PREVIEW')) {
            file_put_contents($preview, $response->getContent());
        }
    }
}
