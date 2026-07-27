<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\ThemeDemoRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeDemoSharedPageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('dedicatedThemeProviders')]
    public function test_dedicated_demo_providers_preserve_an_existing_contact_page(
        string $themeKey,
        string $presetKey,
    ): void {
        $existingPage = $this->createExistingContactPage();
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme($themeKey);

        $this->assertNotNull($provider);
        $result = $provider->generate($presetKey);

        $this->assertSame(0, data_get($result, 'counts.pages'));
        $this->assertSame(1, CmsPage::query()->where('slug', 'contact')->count());
        $this->assertSame('Trang liên hệ do người dùng tạo', $existingPage->fresh()->title);
        $this->assertDatabaseMissing('theme_demo_records', [
            'theme_key' => $themeKey,
            'model_type' => CmsPage::class,
            'model_id' => $existingPage->id,
        ]);
    }

    public function test_generic_demo_generator_preserves_an_existing_contact_page(): void
    {
        $existingPage = $this->createExistingContactPage();

        $result = app(ThemeDemoContentGenerator::class)->generate('TEST-GENERIC', 'electronics-superstore');

        $this->assertSame(1, data_get($result, 'counts.pages'));
        $this->assertSame(1, CmsPage::query()->where('slug', 'contact')->count());
        $this->assertSame('Trang liên hệ do người dùng tạo', $existingPage->fresh()->title);
        $this->assertFalse(ThemeDemoRecord::query()
            ->where('theme_key', 'TEST-GENERIC')
            ->where('model_type', CmsPage::class)
            ->where('model_id', $existingPage->id)
            ->exists());
    }

    public function test_shop601_activation_with_demo_data_succeeds_when_contact_page_exists(): void
    {
        $admin = new Admin([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('test-password'),
            'is_active' => true,
            'status' => 'active',
            'is_system_owner' => true,
        ]);
        $admin->id = Admin::SYSTEM_OWNER_ID;
        $admin->save();
        $existingPage = $this->createExistingContactPage();
        $existingPage->forceFill(['title' => 'Trang liên hệ hiện có'])->save();

        $this->actingAs($admin, 'admin')
            ->postJson('/admin/api/themes/SHOP601/activate', ['create_demo_data' => true])
            ->assertOk();

        $this->assertDatabaseHas('site_profiles', ['active_theme_key' => 'SHOP601']);
        $this->assertSame(1, CmsPage::query()->where('slug', 'contact')->count());
        $this->assertSame('Trang liên hệ hiện có', $existingPage->fresh()->title);
    }

    public static function dedicatedThemeProviders(): array
    {
        return [
            'SHOP601' => ['SHOP601', 'shop601-bean-style'],
            'SHOP602' => ['SHOP602', 'shop602-wolf-yoga'],
            'SHOP603' => ['SHOP603', 'shop603-alena-fashion'],
            'NT502' => ['NT502', 'nt502-dola-furniture'],
        ];
    }

    private function createExistingContactPage(): CmsPage
    {
        return CmsPage::query()->create([
            'title' => 'Trang liên hệ do người dùng tạo',
            'slug' => 'contact',
            'status' => 'published',
            'excerpt' => 'Nội dung cần được giữ nguyên khi đổi theme.',
            'body' => '<p>Không ghi đè nội dung này.</p>',
            'publish_at' => now(),
        ]);
    }
}
