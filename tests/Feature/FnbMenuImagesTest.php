<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbService;
use Tests\TestCase;

class FnbMenuImagesTest extends TestCase
{
    use RefreshDatabase;

    private FnbContext $ctx;
    private FnbService $fnb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(Admin::findOrFail(1), 'admin');
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
        $this->fnb = app(FnbService::class);
        $website = app(SiteContext::class)->websiteKey();
        $onboard = $this->fnb->onboard(new FnbContext($website, 0, 1), [
            'outlet' => ['code' => 'IMAGES', 'name' => 'Image fixture', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS', 'name' => 'POS', 'type' => 'pos'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'table_count' => 0,
        ], (string) Str::uuid())['resource'];
        $this->ctx = new FnbContext($website, $onboard['outlet']['id'], 1, $onboard['terminal']['id']);
        $this->withHeaders(['X-FNB-Outlet' => $this->ctx->outletId, 'X-FNB-Terminal' => $this->ctx->terminalId]);
        Storage::fake('public');
    }

    private function itemInput(): array
    {
        return ['code' => 'PHOTO', 'name' => 'Photo item', 'station_id' => (int) DB::table('fnb_prep_stations')->value('id'),
            'variants' => [['code' => 'S', 'name' => 'Small', 'base_price_minor' => 30000, 'is_default' => true]]];
    }

    public function test_image_upload_checks_file_content_size_scope_and_idempotency(): void
    {
        $image = UploadedFile::fake()->image('dish.png', 100, 80);
        $response = $this->withHeader('Idempotency-Key', 'image-upload-test')->postJson('/admin/api/fnb/menu/images', ['file' => $image]);
        $response->assertOk();
        $url = $response->json('resource.url');
        $this->assertStringContainsString('/fnb/'.hash('sha256', $this->ctx->websiteKey).'/menu/', $url);
        $files = Storage::disk('public')->allFiles();
        $this->assertCount(1, $files);
        Storage::disk('public')->assertExists($files[0]);
        $this->withHeader('Idempotency-Key', 'image-upload-test')->postJson('/admin/api/fnb/menu/images', ['file' => $image])
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('resource.url', $url);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->withHeader('Idempotency-Key', 'not-an-image')->postJson('/admin/api/fnb/menu/images', ['file' => UploadedFile::fake()->createWithContent('fake.png', '<script>bad</script>')])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', 'oversized-image')->postJson('/admin/api/fnb/menu/images', ['file' => UploadedFile::fake()->image('large.png')->size(5121)])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', 'wide-image-test')->postJson('/admin/api/fnb/menu/images', ['file' => UploadedFile::fake()->image('wide.png', 8001, 1)])->assertUnprocessable();
        $this->withHeader('X-FNB-Outlet', 999999)->withHeader('Idempotency-Key', 'wrong-scope-image')->postJson('/admin/api/fnb/menu/images', ['file' => $image])->assertForbidden();
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_gallery_and_selected_cover_survive_save_read_and_legacy_updates(): void
    {
        $images = ['https://example.test/coffee-1.png', 'https://example.test/coffee-2.png'];
        $item = $this->fnb->saveCatalogItem($this->ctx, $this->itemInput() + ['gallery_images' => $images, 'image_url' => $images[1]], 'save-gallery')['resource'];
        $this->assertSame($images, $item['gallery_images']);
        $this->assertSame($images[1], $item['image_url']);
        $this->getJson('/admin/api/fnb/outlets/'.$this->ctx->outletId.'/menu?draft=1')->assertOk()->assertJsonFragment(['gallery_images' => $images, 'image_url' => $images[1]]);
        $update = $this->itemInput();
        $update['variants'][0]['id'] = $item['variants'][0]['id'];
        $legacy = $this->fnb->saveCatalogItem($this->ctx, $update + ['id' => $item['id']], 'legacy-no-gallery', $item['version'])['resource'];
        $this->assertSame($images, $legacy['gallery_images']);
        $this->assertSame($images[1], $legacy['image_url']);
        $updated = $this->fnb->saveCatalogItem($this->ctx, $update + ['id' => $item['id'], 'gallery_images' => [$images[0]]], 'remove-cover', $legacy['version'])['resource'];
        $this->assertSame([$images[0]], $updated['gallery_images']);
        $this->assertSame($images[0], $updated['image_url']);
        $empty = $this->fnb->saveCatalogItem($this->ctx, $update + ['id' => $item['id'], 'gallery_images' => [], 'image_url' => null], 'clear-gallery', $updated['version'])['resource'];
        $this->assertSame([], $empty['gallery_images']);
        $this->assertNull($empty['image_url']);
    }

    public function test_gallery_rejects_unsupported_urls_excess_images_and_unlisted_cover(): void
    {
        foreach ([['javascript:alert(1)'], ['data:image/png;base64,AAA'], array_fill(0, 13, 'https://example.test/a.png')] as $images) {
            try {
                $this->fnb->saveCatalogItem($this->ctx, $this->itemInput() + ['gallery_images' => $images], (string) Str::uuid());
                $this->fail('Invalid gallery must fail validation.');
            } catch (\Illuminate\Validation\ValidationException) { $this->assertDatabaseMissing('fnb_menu_items', ['code' => 'PHOTO']); }
        }
        try {
            $this->fnb->saveCatalogItem($this->ctx, $this->itemInput() + ['gallery_images' => ['https://example.test/a.png'], 'image_url' => 'https://example.test/b.png'], 'invalid-cover');
            $this->fail('Cover must belong to the gallery.');
        } catch (\Modules\FnbPos\Domain\FnbValidationException) { $this->assertDatabaseMissing('fnb_menu_items', ['code' => 'PHOTO']); }
    }

    public function test_image_upload_requires_menu_management_permissions(): void
    {
        $staff = Admin::factory()->create(['must_change_password' => false]);
        DB::transaction(fn () => app(\Modules\FnbPos\Services\Security\FnbStaffAssignmentService::class)->assign(Admin::findOrFail(1), $this->ctx->websiteKey, $this->ctx->outletId, $staff, 'fnb-manager'));
        $staff = $staff->fresh();
        $this->actingAs($staff, 'admin')->withSession(['admin_auth_version' => $staff->auth_version])->withHeader('Idempotency-Key', 'unauthorized-image')
            ->postJson('/admin/api/fnb/menu/images', ['file' => UploadedFile::fake()->image('photo.png')])->assertForbidden();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
