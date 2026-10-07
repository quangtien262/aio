<?php

namespace Tests\Feature;

use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use Database\Seeders\Nt501ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Nt501ProjectSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_both_sections_for_nt501_websites_without_duplicates(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'Interior Studio', 'active_theme_key' => 'NT501', 'website_type' => 'service']);
        Site::create(['domain' => 'interior.demo.test', 'website_key' => 'interior', 'theme_key' => 'NT501', 'status' => 'active']);
        Site::create(['domain' => 'other.demo.test', 'website_key' => 'other', 'theme_key' => 'DN351', 'status' => 'active']);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'NT501', true);

        $this->seed(Nt501ProjectSeeder::class);
        $this->seed(Nt501ProjectSeeder::class);

        $this->assertSame(6, CmsProject::query()->forWebsite('website-main')->count());
        $this->assertSame(6, CmsProject::query()->forWebsite('interior')->count());
        $this->assertSame(0, CmsProject::query()->forWebsite('other')->count());
        $this->assertSame(12, CmsProjectImage::count());
        $this->assertSame(24, ThemeDemoRecord::withoutGlobalScope('current_website')->count());

        $response = $this->get('/vi')->assertOk()->assertDontSee('Đang cập nhật dự án.');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['nt-showcase__card' => 2, 'nt-project-card' => 6] as $class => $count) {
            $cards = $xpath->query('//article[@class="'.$class.'"]');
            $this->assertSame($count, $cards->length);
            foreach ($cards as $card) {
                $image = $xpath->query('.//img', $card)->item(0);
                $this->assertFileExists(public_path(parse_url($image->getAttribute('src'), PHP_URL_PATH)));
                $this->get($xpath->query('.//a', $card)->item(0)->getAttribute('href'))->assertOk();
            }
        }
    }

    public function test_seeder_preserves_existing_manual_projects(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'active_theme_key' => 'NT501']);
        $project = CmsProject::create([
            'title' => 'Nội dung nhập tay',
            'slug' => Str::slug('NT501-Căn hộ tối giản 75 m²'),
            'summary' => 'Giữ nguyên nội dung',
            'status' => 'draft',
        ]);

        $this->seed(Nt501ProjectSeeder::class);

        $this->assertSame('Nội dung nhập tay', $project->fresh()->title);
        $this->assertSame('Giữ nguyên nội dung', $project->fresh()->summary);
        $this->assertSame('draft', $project->fresh()->status);
        $this->assertSame(0, $project->images()->count());
        $this->assertFalse(ThemeDemoRecord::where('model_type', CmsProject::class)->where('model_id', $project->id)->exists());
        $this->assertSame(6, CmsProject::count());
    }
}
