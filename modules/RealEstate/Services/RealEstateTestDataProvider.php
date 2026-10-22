<?php

namespace Modules\RealEstate\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Cms\Services\CmsTestDataProvider;

final class RealEstateTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một loại bất động sản TEST đang tắt và một tin căn hộ ở trạng thái nháp.', 'Không công khai tin, không dùng địa chỉ hoặc liên hệ người thật.'];
    }

    public function references(): array
    {
        return [...CmsTestDataProvider::contentReferences('real_estate_property_type', 'real_estate_property_types'), ...CmsTestDataProvider::contentReferences('real_estate_listing', 'real_estate_listings')];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $values = ['name' => '[TEST] Căn hộ mẫu', 'slug' => Str::slug($context->code('TYPE')), 'description' => 'Loại bất động sản thử nghiệm đang tắt.'];
        $type = $context->insert('real_estate_property_types', $values + ['is_active' => false]);
        CmsTestDataProvider::draftTranslation($context, 'real_estate_property_type', $type, $values);
        $values = ['title' => '[TEST] Tin căn hộ mẫu chưa phát hành', 'slug' => Str::slug($context->code('LISTING')), 'summary' => 'Tin thử nghiệm để kiểm tra biên tập.', 'content' => '<p>Đây là bất động sản giả lập, không phải thông tin rao bán thực.</p>'];
        $id = $context->insert('real_estate_listings', $values + ['code' => $context->code('BDS'), 'property_type_id' => $type, 'publication_status' => 'draft', 'availability_status' => 'available', 'transaction_type' => 'sale', 'price' => '2000000000.00', 'currency' => 'VND', 'land_area' => '60.00', 'bedrooms' => 2, 'bathrooms' => 1, 'is_featured' => false, 'is_hot' => false]);
        CmsTestDataProvider::draftTranslation($context, 'real_estate_listing', $id, $values);
    }
}
