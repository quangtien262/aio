<?php

namespace Modules\Catalog\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Cms\Services\CmsTestDataProvider;

final class CatalogTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một danh mục và hai sản phẩm TEST đang tắt, tồn bằng 0.', 'Bản dịch nguồn nháp; không thay tồn, giá hoặc danh mục sản phẩm thật.'];
    }

    public function references(): array
    {
        $references = [...CmsTestDataProvider::contentReferences('catalog_category', 'catalog_categories'), ...CmsTestDataProvider::contentReferences('catalog_product', 'catalog_products')];
        $references[] = ['table' => 'acct_item_sources', 'column' => 'source_id', 'foreign_table' => 'catalog_products', 'foreign_column' => 'id', 'where' => ['source_module' => 'catalog', 'source_type' => 'catalog.product']];
        foreach (['inv_items', 'inv_sync_run_lines', 'order_items', 'customer_favorites'] as $table) {
            $references[] = ['table' => $table, 'column' => 'catalog_product_id', 'foreign_table' => 'catalog_products', 'foreign_column' => 'id'];
        }

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $values = ['name' => '[TEST] Danh mục sản phẩm mẫu', 'slug' => Str::slug($context->code('CATEGORY')), 'description' => 'Danh mục thử nghiệm đang tắt.'];
        $category = $context->insert('catalog_categories', $values + ['is_active' => false]);
        CmsTestDataProvider::draftTranslation($context, 'catalog_category', $category, $values);
        foreach (['Sản phẩm A' => '100000.00', 'Sản phẩm B' => '150000.00'] as $name => $price) {
            $values = ['name' => '[TEST] '.$name, 'slug' => Str::slug($context->code('PRODUCT')), 'short_description' => 'Sản phẩm thử nghiệm chưa mở bán.', 'detail_content' => '<p>Mô tả sản phẩm mẫu.</p>'];
            $id = $context->insert('catalog_products', $values + ['sku' => $context->code('SKU'), 'catalog_category_id' => $category, 'price' => $price, 'stock' => 0, 'is_active' => false, 'is_featured' => false]);
            CmsTestDataProvider::draftTranslation($context, 'catalog_product', $id, $values);
        }
    }
}
