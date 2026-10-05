<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreviewAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_original_preview_urls_redirect_to_optimized_images(): void
    {
        foreach (['SHOP602/602', 'AUTO850/preview-auto850', 'BZ501/avatar'] as $image) {
            $this->assertFileDoesNotExist(public_path('theme-previews/'.$image.'.png'));
            $this->assertFileExists(public_path('theme-previews/'.$image.'-optimized.webp'));
            $this->get('/theme-previews/'.$image.'.png')
                ->assertStatus(301)
                ->assertRedirect('/theme-previews/'.$image.'-optimized.webp');
        }
    }

    public function test_missing_previews_do_not_redirect_to_nonexistent_images(): void
    {
        $this->get('/theme-previews/SHOP602/nonexistent.png')->assertNotFound();
        $this->get('/theme-previews/nonexistent/602.png')->assertNotFound();
        $this->get('/theme-previews/SHOP602/602-optimized.webp')->assertNotFound();
    }

    public function test_optimized_avatars_remain_discoverable_after_original_removal(): void
    {
        $themes = app(ThemeRegistry::class)->all()->keyBy('key');

        foreach (['BZ501', 'FOOT401'] as $key) {
            $this->assertStringEndsWith('/theme-previews/'.$key.'/avatar-optimized.webp', $themes[$key]['avatar_url']);
        }
    }
}
