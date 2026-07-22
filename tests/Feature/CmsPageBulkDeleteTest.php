<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsPageBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_selected_pages_for_current_website_only(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $first = CmsPage::query()->create([
            'title' => 'Page one',
            'slug' => 'page-one',
            'status' => 'draft',
        ]);
        $second = CmsPage::query()->create([
            'title' => 'Page two',
            'slug' => 'page-two',
            'status' => 'published',
        ]);
        $kept = CmsPage::query()->create([
            'title' => 'Page kept',
            'slug' => 'page-kept',
            'status' => 'draft',
        ]);
        $foreignId = DB::table('cms_pages')->insertGetId([
            'website_key' => 'website-foreign',
            'title' => 'Foreign page',
            'slug' => 'foreign-page',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/admin/api/cms/pages/bulk', [
            'ids' => [$first->id, $second->id, $foreignId],
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('cms_pages', ['id' => $first->id]);
        $this->assertDatabaseMissing('cms_pages', ['id' => $second->id]);
        $this->assertDatabaseHas('cms_pages', ['id' => $kept->id]);
        $this->assertDatabaseHas('cms_pages', ['id' => $foreignId, 'website_key' => 'website-foreign']);
    }
}
