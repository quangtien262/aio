<?php

namespace Tests\Feature;

use App\Support\SiteContentCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteContentCopierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_copies_domain_content_and_preserves_category_and_image_relations(): void
    {
        $sourceKey = 'source-site';
        $targetKey = 'target-site';
        $now = now();

        $productParentId = DB::table('catalog_categories')->insertGetId([
            'name' => 'Đồ uống',
            'slug' => 'do-uong',
            'website_key' => $sourceKey,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productChildId = DB::table('catalog_categories')->insertGetId([
            'parent_id' => $productParentId,
            'name' => 'Cà phê',
            'slug' => 'ca-phe',
            'website_key' => $sourceKey,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('catalog_products')->insertGetId([
            'catalog_category_id' => $productChildId,
            'name' => 'Cà phê sữa',
            'slug' => 'ca-phe-sua',
            'sku' => 'CF-001',
            'price' => 45000,
            'stock' => 20,
            'website_key' => $sourceKey,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('catalog_product_images')->insert([
            'catalog_product_id' => $productId,
            'image_url' => 'https://example.com/coffee.jpg',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $postCategoryId = $this->insertCategory('cms_categories', $sourceKey, 'Kiến thức', 'kien-thuc', $now);
        $mediaId = DB::table('cms_media')->insertGetId([
            'title' => 'Ảnh bài viết',
            'file_path' => 'uploads/article.jpg',
            'file_url' => '/storage/uploads/article.jpg',
            'size' => 100,
            'website_key' => $sourceKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cms_posts')->insert([
            'category_id' => $postCategoryId,
            'featured_media_id' => $mediaId,
            'title' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
            'status' => 'published',
            'website_key' => $sourceKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $serviceCategoryId = $this->insertCategory('cms_service_categories', $sourceKey, 'Tư vấn', 'tu-van', $now);
        $serviceId = DB::table('cms_services')->insertGetId([
            'cms_service_category_id' => $serviceCategoryId,
            'title' => 'Tư vấn thiết kế',
            'slug' => 'tu-van-thiet-ke',
            'status' => 'published',
            'website_key' => $sourceKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cms_service_images')->insert([
            'cms_service_id' => $serviceId,
            'cms_media_id' => $mediaId,
            'image_url' => '/storage/uploads/article.jpg',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $projectCategoryId = $this->insertCategory('cms_project_categories', $sourceKey, 'Nhà phố', 'nha-pho', $now);
        $projectId = DB::table('cms_projects')->insertGetId([
            'cms_project_category_id' => $projectCategoryId,
            'title' => 'Dự án mẫu',
            'slug' => 'du-an-mau',
            'status' => 'published',
            'website_key' => $sourceKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cms_project_images')->insert([
            'cms_project_id' => $projectId,
            'cms_media_id' => $mediaId,
            'image_url' => '/storage/uploads/article.jpg',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $counts = app(SiteContentCopier::class)->copy($sourceKey, $targetKey);

        $this->assertSame(2, $counts['product_categories']);
        $this->assertSame(1, $counts['products']);
        $this->assertSame(1, $counts['posts']);
        $this->assertSame(1, $counts['services']);
        $this->assertSame(1, $counts['projects']);

        $targetParentId = DB::table('catalog_categories')
            ->where('website_key', $targetKey)
            ->where('slug', 'do-uong')
            ->value('id');
        $targetChild = DB::table('catalog_categories')
            ->where('website_key', $targetKey)
            ->where('slug', 'ca-phe')
            ->first();
        $targetProduct = DB::table('catalog_products')
            ->where('website_key', $targetKey)
            ->where('sku', 'CF-001')
            ->first();

        $this->assertSame((int) $targetParentId, (int) $targetChild->parent_id);
        $this->assertSame((int) $targetChild->id, (int) $targetProduct->catalog_category_id);
        $this->assertDatabaseHas('catalog_product_images', [
            'catalog_product_id' => $targetProduct->id,
            'image_url' => 'https://example.com/coffee.jpg',
        ]);

        $targetMediaId = DB::table('cms_media')
            ->where('website_key', $targetKey)
            ->where('file_path', 'uploads/article.jpg')
            ->value('id');
        $this->assertNotSame($mediaId, (int) $targetMediaId);
        $this->assertDatabaseHas('cms_posts', [
            'website_key' => $targetKey,
            'slug' => 'bai-viet-mau',
            'featured_media_id' => $targetMediaId,
        ]);
        $this->assertDatabaseHas('cms_service_images', ['cms_media_id' => $targetMediaId]);
        $this->assertDatabaseHas('cms_project_images', ['cms_media_id' => $targetMediaId]);

        DB::table('catalog_products')->where('id', $productId)->update(['name' => 'Cà phê sữa mới']);
        app(SiteContentCopier::class)->copy($sourceKey, $targetKey);

        $this->assertSame(1, DB::table('catalog_products')->where('website_key', $targetKey)->count());
        $this->assertSame(
            'Cà phê sữa mới',
            DB::table('catalog_products')->where('website_key', $targetKey)->value('name'),
        );
    }

    private function insertCategory(string $table, string $websiteKey, string $name, string $slug, mixed $now): int
    {
        $attributes = [
            'name' => $name,
            'slug' => $slug,
            'website_key' => $websiteKey,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($table !== 'cms_categories') {
            $attributes['sort_order'] = 0;
            $attributes['is_active'] = true;
        }

        return DB::table($table)->insertGetId($attributes);
    }
}
