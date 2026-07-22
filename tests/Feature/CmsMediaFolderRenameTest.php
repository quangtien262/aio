<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CmsMedia;
use App\Models\CmsMediaFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsMediaFolderRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rename_media_folder_without_losing_its_media(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $folder = CmsMediaFolder::query()->create([
            'name' => 'Banner cũ',
            'path' => 'banner-cu',
            'sort_order' => 1,
        ]);
        $media = CmsMedia::query()->create([
            'title' => 'Hero',
            'file_path' => 'cms/website-main/hero.jpg',
            'file_url' => 'https://example.test/hero.jpg',
            'mime_type' => 'image/external',
            'size' => 0,
            'folder_path' => 'banner-cu',
        ]);

        $this->putJson("/admin/api/cms/media/folders/{$folder->id}", [
            'name' => 'Banner trang chủ',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Banner trang chủ')
            ->assertJsonPath('data.path', 'banner-trang-chu')
            ->assertJsonPath('data.previous_path', 'banner-cu');

        $this->assertDatabaseHas('cms_media_folders', [
            'id' => $folder->id,
            'name' => 'Banner trang chủ',
            'path' => 'banner-trang-chu',
        ]);
        $this->assertDatabaseHas('cms_media', [
            'id' => $media->id,
            'folder_path' => 'banner-trang-chu',
            'file_path' => 'cms/website-main/hero.jpg',
        ]);
    }

    public function test_folder_cannot_be_renamed_to_an_existing_path(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $folder = CmsMediaFolder::query()->create(['name' => 'Banner', 'path' => 'banner', 'sort_order' => 1]);
        CmsMediaFolder::query()->create(['name' => 'Sản phẩm', 'path' => 'san-pham', 'sort_order' => 2]);

        $this->putJson("/admin/api/cms/media/folders/{$folder->id}", [
            'name' => 'Sản phẩm',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('cms_media_folders', ['id' => $folder->id, 'path' => 'banner']);
    }

    public function test_deleting_folder_moves_its_media_to_uncategorized(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $folder = CmsMediaFolder::query()->create(['name' => 'Banner', 'path' => 'banner', 'sort_order' => 1]);
        $media = CmsMedia::query()->create([
            'title' => 'Hero',
            'file_path' => 'cms/website-main/hero.jpg',
            'file_url' => 'https://example.test/hero.jpg',
            'mime_type' => 'image/external',
            'size' => 0,
            'folder_path' => 'banner',
        ]);

        $this->deleteJson("/admin/api/cms/media/folders/{$folder->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted_folder_id', $folder->id)
            ->assertJsonPath('data.moved_media_count', 1);

        $this->assertDatabaseMissing('cms_media_folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('cms_media', [
            'id' => $media->id,
            'folder_path' => null,
            'file_path' => 'cms/website-main/hero.jpg',
        ]);
    }
}
