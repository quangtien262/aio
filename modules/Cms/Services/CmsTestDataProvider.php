<?php

namespace Modules\Cms\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use App\Support\Localization\TranslationRevision;
use Illuminate\Support\Str;

final class CmsTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một trang, hai bài viết và một dịch vụ TEST ở trạng thái nháp.', 'Bản dịch ngôn ngữ nguồn ở trạng thái nháp; không phát hành nội dung hoặc thay menu/theme.'];
    }

    public function references(): array
    {
        $references = [];
        foreach (['cms_page' => 'cms_pages', 'cms_post' => 'cms_posts', 'cms_service' => 'cms_services'] as $type => $table) {
            array_push($references, ...self::contentReferences($type, $table));
        }
        $references[] = ['table' => 'acct_item_sources', 'column' => 'source_id', 'foreign_table' => 'cms_services', 'foreign_column' => 'id', 'where' => ['source_module' => 'cms', 'source_type' => 'cms.service']];
        $references[] = ['table' => 'customer_service_interests', 'column' => 'cms_service_id', 'foreign_table' => 'cms_services', 'foreign_column' => 'id'];

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $page = ['title' => '[TEST] Trang giới thiệu mẫu', 'slug' => Str::slug($context->code('PAGE')), 'excerpt' => 'Nội dung thử nghiệm chưa phát hành.', 'body' => '<p>Đây là trang nháp để thử trình biên tập CMS.</p>', 'meta_title' => null, 'meta_description' => null, 'meta_keywords' => null];
        $id = $context->insert('cms_pages', $page + ['status' => 'draft']);
        $revision = TranslationRevision::fingerprint($page);
        $context->insert('cms_page_translations', $page + ['cms_page_id' => $id, 'locale' => config('localization.source_locale', 'vi'), 'translation_status' => 'draft', 'source_revision' => $revision, 'translation_revision' => $revision, 'is_machine_translated' => false]);
        foreach (['Bài viết giới thiệu', 'Hướng dẫn sử dụng'] as $name) {
            $values = ['title' => '[TEST] '.$name, 'slug' => Str::slug($context->code('POST')), 'excerpt' => 'Bài viết thử nghiệm, chưa công khai.', 'body' => '<p>Nội dung mẫu để thử lưu nháp, tìm kiếm và chỉnh sửa bài viết.</p>'];
            $id = $context->insert('cms_posts', $values + ['status' => 'draft']);
            self::draftTranslation($context, 'cms_post', $id, $values);
        }
        $values = ['title' => '[TEST] Dịch vụ mẫu', 'slug' => Str::slug($context->code('SERVICE')), 'summary' => 'Dịch vụ thử nghiệm chưa phát hành.', 'content' => '<p>Mô tả dịch vụ mẫu.</p>'];
        $id = $context->insert('cms_services', $values + ['status' => 'draft']);
        self::draftTranslation($context, 'cms_service', $id, $values);
    }

    /** Shared localization contract; every translation stays in the initiating batch. */
    public static function draftTranslation(ModuleTestDataContext $context, string $type, int|string $id, array $values): void
    {
        $payload = [];
        foreach (config('localized-content.resources.'.$type.'.fields', []) as $field) {
            $payload[$field] = $values[$field] ?? null;
        }
        $revision = TranslationRevision::fingerprint($payload);
        $context->insert('content_translations', ['resource_type' => $type, 'resource_id' => (string) $id, 'locale' => config('localization.source_locale', 'vi'), 'slug' => $values['slug'] ?? null, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'translation_status' => 'draft', 'source_revision' => $revision, 'translation_revision' => $revision, 'is_machine_translated' => false]);
    }

    public static function contentReferences(string $type, string $table): array
    {
        return array_map(fn (string $child): array => ['table' => $child, 'column' => 'resource_id', 'foreign_table' => $table, 'foreign_column' => 'id', 'where' => ['resource_type' => $type]], ['content_translations', 'localized_routes', 'content_api_resource_links']);
    }
}
