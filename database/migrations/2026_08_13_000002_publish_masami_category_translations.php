<?php

use App\Enums\TranslationStatus;
use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsServiceCategory;
use App\Models\ContentTranslation;
use App\Support\Localization\LocalizedContentRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_translations') || ! Schema::hasTable('localized_routes')) {
            return;
        }

        $records = [
            [CatalogCategory::class, 'catalog_category', 'Hoá chất sản xuất', [
                'name' => 'Production chemicals',
                'slug' => 'production-chemicals',
                'description' => 'Production chemicals including caustic soda flakes, basic acids and industrial cleaning agents.',
                'meta_title' => 'Production chemicals',
                'meta_description' => 'Reliable production chemicals and industrial cleaning solutions for factories.',
            ]],
            [CatalogCategory::class, 'catalog_category', 'Nhiên liệu sản xuất', [
                'name' => 'Production fuels',
                'slug' => 'production-fuels',
                'description' => 'Production fuels including LNG, CNG, LPG, liquid hydrogen and industrial gases.',
                'meta_title' => 'Production fuels',
                'meta_description' => 'Industrial fuels and gas solutions for safe, efficient production.',
            ]],
            [CatalogCategory::class, 'catalog_category', 'Vận hành', [
                'name' => 'Operations supplies',
                'slug' => 'operations-supplies',
                'description' => 'Operational supplies such as lubricants, coolants and industrial greases.',
                'meta_title' => 'Operations supplies',
                'meta_description' => 'Essential supplies for stable and efficient industrial operations.',
            ]],
            [CatalogCategory::class, 'catalog_category', 'Xây dựng và nền móng', [
                'name' => 'Construction and foundations',
                'slug' => 'construction-and-foundations',
                'description' => 'Construction and foundation products for factories, waterproofing, insulation and roof protection.',
                'meta_title' => 'Construction and foundation products',
                'meta_description' => 'Construction, waterproofing and foundation solutions for industrial facilities.',
            ]],
            [CmsServiceCategory::class, 'cms_service_category', 'Dịch vụ', [
                'name' => 'Industrial services',
                'slug' => 'industrial-services',
                'description' => 'MASAMI industrial consulting, supply and technical support services.',
                'meta_title' => 'Industrial services',
                'meta_description' => 'Industrial consulting, supply and technical support from MASAMI.',
            ]],
            [CmsCategory::class, 'cms_category', 'Kiến thức và kinh nghiệm', [
                'name' => 'Knowledge and experience',
                'slug' => 'knowledge-and-experience',
                'description' => 'Practical knowledge, experience and updates for industrial businesses.',
                'meta_title' => 'Industrial knowledge and experience',
                'meta_description' => 'Practical insights and updates for industrial operations and development.',
            ]],
        ];

        $repository = app(LocalizedContentRepository::class);

        foreach ($records as [$modelClass, $resourceType, $sourceName, $payload]) {
            /** @var Model|null $record */
            $record = $modelClass::query()
                ->withoutGlobalScopes()
                ->where('website_key', 'website-main')
                ->where('name', $sourceName)
                ->first();

            if ($record === null || ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', 'website-main')
                ->where('resource_type', $resourceType)
                ->where('resource_id', (string) $record->getKey())
                ->where('locale', 'en')
                ->exists()) {
                continue;
            }

            $translation = $repository->saveDraftPayload(
                'website-main',
                $resourceType,
                (string) $record->getKey(),
                'en',
                $payload,
                false,
                true,
            );
            $translation = $repository->transition($translation, TranslationStatus::Ready);
            $repository->transition($translation, TranslationStatus::Published);
        }
    }

    public function down(): void
    {
        // Published customer-facing translations are not removed automatically.
    }
};
