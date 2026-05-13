<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\CmsPage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDemoContentTh0002Test extends TestCase
{
    use RefreshDatabase;

    public function test_garment_workshop_demo_preset_generates_line_specific_copy_and_renders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->activateThemePreset('garment-workshop');

        $aboutPage = CmsPage::query()->where('slug', 'demo-th0002-gioi-thieu')->firstOrFail();
        $contactPage = CmsPage::query()->where('slug', 'demo-th0002-lien-he')->firstOrFail();
        $oemProduct = CatalogProduct::query()->where('name', 'like', '%Techpack cơ bản%')->firstOrFail();
        $uniformProduct = CatalogProduct::query()->where('name', 'like', '%Polo công ty%')->firstOrFail();
        $lookbookPost = \App\Models\CmsPost::query()->orderBy('id')->firstOrFail();

        $this->assertStringContainsString('đồng phục doanh nghiệp, local brand capsule và line OEM / ODM', (string) $aboutPage->body);
        $this->assertStringContainsString('luồng nhận techpack cho đồng phục, capsule local brand và đơn OEM / ODM', (string) $contactPage->body);
        $this->assertStringContainsString('doanh nghiệp cần đồng bộ hình ảnh theo bộ size và màu thương hiệu', (string) $uniformProduct->short_description);
        $this->assertStringContainsString('brand cần đối tác OEM / ODM có thể nhận brief và hoàn thiện mẫu', (string) $oemProduct->short_description);
        $this->assertStringContainsString('quy trình nhận techpack và kiểm soát mẫu trước sản xuất', (string) $oemProduct->detail_content);
        $this->assertStringContainsString('checklist size run, brief in thêu và mốc duyệt mẫu trước khi vào chuyền', (string) $lookbookPost->body);

        $this->get($this->storefrontPath('demo-th0002-gioi-thieu'))
            ->assertOk()
            ->assertSee('đồng phục doanh nghiệp, local brand capsule và line OEM / ODM')
            ->assertSee('Về xưởng may');

        $this->get($this->storefrontPath('tin-tuc'))
            ->assertOk()
            ->assertSee('Lookbook')
            ->assertSee('Lookbook capsule mới cho line xưởng may');
    }

    public function test_fashion_studio_demo_preset_generates_collection_specific_copy_and_renders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->activateThemePreset('fashion-studio');

        $aboutPage = CmsPage::query()->where('slug', 'demo-th0002-gioi-thieu')->firstOrFail();
        $contactPage = CmsPage::query()->where('slug', 'demo-th0002-lien-he')->firstOrFail();
        $blazerProduct = CatalogProduct::query()->where('name', 'like', '%Blazer%')->firstOrFail();
        $accessoryProduct = CatalogProduct::query()->where('name', 'like', '%Canvas tote%')->firstOrFail();
        $lookbookPost = \App\Models\CmsPost::query()->orderBy('id')->firstOrFail();

        $this->assertStringContainsString('new season capsule, ready-to-wear nữ và lookbook set theo dịp sử dụng', (string) $aboutPage->body);
        $this->assertStringContainsString('lịch stylist cho capsule mới, line ready-to-wear và lookbook set theo mùa', (string) $contactPage->body);
        $this->assertStringContainsString('phom blazer, dress và wide-leg để lên set đồ hoàn chỉnh', (string) $blazerProduct->short_description);
        $this->assertStringContainsString('canvas tote, cap và belt để tăng giá trị outfit', (string) $accessoryProduct->short_description);
        $this->assertStringContainsString('ready-to-wear nữ, cân bằng giữa phom dáng ứng dụng và visual showroom', (string) $blazerProduct->detail_content);
        $this->assertStringContainsString('editorial note cho từng drop, caption cho lookbook card và CTA đặt lịch thử đồ', (string) $lookbookPost->body);

        $this->get($this->storefrontPath('demo-th0002-lien-he'))
            ->assertOk()
            ->assertSee('Đặt lịch stylist &amp; tư vấn', false)
            ->assertSee('lịch stylist cho capsule mới, line ready-to-wear và lookbook set theo mùa');

        $this->get($this->storefrontPath('tin-tuc'))
            ->assertOk()
            ->assertSee('Lookbook')
            ->assertSee('Lookbook season mới cho line thời trang');
    }

    private function activateThemePreset(string $preset): void
    {
        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/themes/TH0002/activate')->assertOk();
        $this->postJson('/admin/api/themes/TH0002/demo-data', [
            'preset' => $preset,
        ])->assertOk();
    }
}
