<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteMappingCopyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_copy_content_between_site_mappings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(Admin::query()->where('email', 'admin@aio.local')->firstOrFail(), 'admin');

        $source = Site::query()->create([
            'domain' => 'source.demo.test',
            'website_key' => 'copy-source',
            'theme_key' => 'FOOT403',
            'status' => 'active',
        ]);
        $target = Site::query()->create([
            'domain' => 'target.demo.test',
            'website_key' => 'copy-target',
            'theme_key' => 'FOOT403',
            'status' => 'active',
        ]);

        $categoryId = DB::table('catalog_categories')->insertGetId([
            'name' => 'API Category',
            'slug' => 'api-category',
            'website_key' => $source->website_key,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('catalog_products')->insert([
            'catalog_category_id' => $categoryId,
            'name' => 'API Product',
            'slug' => 'api-product',
            'sku' => 'API-COPY-001',
            'price' => 100000,
            'stock' => 5,
            'website_key' => $source->website_key,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/admin/api/site-mappings/{$source->id}/copy-content", [
            'target_site_id' => $target->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.source.id', $source->id)
            ->assertJsonPath('data.target.id', $target->id)
            ->assertJsonPath('data.counts.products', 1)
            ->assertJsonPath('data.counts.product_categories', 1);

        $this->assertDatabaseHas('catalog_products', [
            'website_key' => $target->website_key,
            'sku' => 'API-COPY-001',
            'name' => 'API Product',
        ]);
    }
}
