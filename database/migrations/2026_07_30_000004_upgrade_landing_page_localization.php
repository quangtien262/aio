<?php

use App\Enums\TranslationStatus;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\TranslationRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('landing_page_data', 'slug')) {
            Schema::table('landing_page_data', function (Blueprint $table): void {
                $table->string('slug')->nullable()->after('locale')->index();
            });
        }

        if (! Schema::hasColumn('landing_page_blocks', 'schema_version')) {
            Schema::table('landing_page_blocks', function (Blueprint $table): void {
                $table->unsignedSmallInteger('schema_version')->default(1)->after('block_type');
            });
        }

        if (! Schema::hasColumn('landing_page_block_data', 'schema_version')) {
            Schema::table('landing_page_block_data', function (Blueprint $table): void {
                $table->unsignedSmallInteger('schema_version')->default(1)->after('locale');
            });
        }

        $this->classifyPageTranslations();
        $this->classifyBlockTranslations();
        $this->backfillRoutes();
        $this->backfillContentRoutes();
    }

    public function down(): void
    {
        if (Schema::hasColumn('landing_page_block_data', 'schema_version')) {
            Schema::table('landing_page_block_data', function (Blueprint $table): void {
                $table->dropColumn('schema_version');
            });
        }

        if (Schema::hasColumn('landing_page_blocks', 'schema_version')) {
            Schema::table('landing_page_blocks', function (Blueprint $table): void {
                $table->dropColumn('schema_version');
            });
        }

        if (Schema::hasColumn('landing_page_data', 'slug')) {
            Schema::table('landing_page_data', function (Blueprint $table): void {
                $table->dropColumn('slug');
            });
        }
    }

    private function classifyPageTranslations(): void
    {
        $sourceLocale = (string) config('localization.source_locale', 'vi');

        DB::table('landing_pages')->orderBy('id')->chunkById(100, function ($pages) use ($sourceLocale): void {
            foreach ($pages as $page) {
                $rows = DB::table('landing_page_data')
                    ->where('landing_page_id', $page->id)
                    ->get();
                $source = $rows->firstWhere('locale', $sourceLocale) ?? $rows->first();

                foreach ($rows as $row) {
                    $payload = $this->pagePayload($row, (string) $page->slug);
                    $sourcePayload = $source
                        ? $this->pagePayload($source, (string) $page->slug)
                        : $payload;
                    $isSource = (string) $row->locale === $sourceLocale;
                    $isIdentical = ! $isSource && $payload === $sourcePayload;
                    $status = $isSource
                        ? ((string) $page->status === 'published'
                            ? TranslationStatus::Published
                            : TranslationStatus::Draft)
                        : ($isIdentical
                            ? TranslationStatus::NeedsTranslation
                            : (TranslationStatus::tryFrom((string) $row->translation_status)
                                ?? TranslationStatus::Published));

                    DB::table('landing_page_data')->where('id', $row->id)->update([
                        'slug' => (string) $page->slug,
                        'translation_status' => $status->value,
                        'source_revision' => TranslationRevision::fingerprint($sourcePayload),
                        'translation_revision' => TranslationRevision::fingerprint($payload),
                        'translation_meta' => json_encode([
                            'migration' => 'landing_localization_v2',
                            'requires_human_translation' => $isIdentical,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'translation_published_at' => $status === TranslationStatus::Published
                            ? ($row->translation_published_at ?? $page->published_at ?? $row->updated_at)
                            : null,
                    ]);
                }
            }
        });
    }

    private function classifyBlockTranslations(): void
    {
        $sourceLocale = (string) config('localization.source_locale', 'vi');

        DB::table('landing_page_blocks')->orderBy('id')->chunkById(100, function ($blocks) use ($sourceLocale): void {
            foreach ($blocks as $block) {
                $rows = DB::table('landing_page_block_data')
                    ->where('landing_page_block_id', $block->id)
                    ->get();
                $source = $rows->firstWhere('locale', $sourceLocale) ?? $rows->first();

                foreach ($rows as $row) {
                    $payload = $this->blockPayload($row);
                    $sourcePayload = $source ? $this->blockPayload($source) : $payload;
                    $isSource = (string) $row->locale === $sourceLocale;
                    $isIdentical = ! $isSource && $payload === $sourcePayload;
                    $status = $isSource
                        ? TranslationStatus::Published
                        : ($isIdentical
                            ? TranslationStatus::NeedsTranslation
                            : (TranslationStatus::tryFrom((string) $row->translation_status)
                                ?? TranslationStatus::Published));

                    DB::table('landing_page_block_data')->where('id', $row->id)->update([
                        'schema_version' => (int) ($block->schema_version ?? 1),
                        'translation_status' => $status->value,
                        'source_revision' => TranslationRevision::fingerprint($sourcePayload),
                        'translation_revision' => TranslationRevision::fingerprint($payload),
                        'translation_meta' => json_encode([
                            'migration' => 'landing_block_schema_v1',
                            'requires_human_translation' => $isIdentical,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'translation_published_at' => $status === TranslationStatus::Published
                            ? ($row->translation_published_at ?? $row->updated_at)
                            : null,
                    ]);
                }
            }
        });
    }

    private function backfillRoutes(): void
    {
        if (! Schema::hasTable('localized_routes')) {
            return;
        }

        DB::table('localized_routes')
            ->where('resource_type', 'landing_page')
            ->where('path', '/')
            ->delete();

        $timestamp = now();
        $rows = DB::table('landing_page_data')
            ->join('landing_pages', 'landing_pages.id', '=', 'landing_page_data.landing_page_id')
            ->where('landing_page_data.translation_status', TranslationStatus::Published->value)
            ->where('landing_pages.is_home', false)
            ->select([
                'landing_page_data.landing_page_id',
                'landing_page_data.locale',
                'landing_page_data.slug',
                'landing_pages.website_key',
                'landing_pages.is_home',
            ])
            ->get();

        foreach ($rows as $row) {
            $slug = trim((string) $row->slug);
            $path = '/land/'.$slug;

            DB::table('localized_routes')->updateOrInsert(
                [
                    'website_key' => $row->website_key,
                    'locale' => $row->locale,
                    'resource_type' => 'landing_page',
                    'resource_id' => (string) $row->landing_page_id,
                    'path' => $path,
                ],
                [
                    'route_name' => 'site.landing.show',
                    'is_canonical' => true,
                    'is_published' => true,
                    'redirect_to' => null,
                    'metadata' => json_encode(['slug' => $slug], JSON_UNESCAPED_UNICODE),
                    'published_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );
        }
    }

    private function backfillContentRoutes(): void
    {
        if (! Schema::hasTable('content_translations') || ! Schema::hasTable('localized_routes')) {
            return;
        }

        $timestamp = now();
        $translations = DB::table('content_translations')
            ->where('translation_status', TranslationStatus::Published->value)
            ->whereNotNull('slug')
            ->whereIn('resource_type', [
                'cms_post',
                'cms_service',
                'cms_project',
                'catalog_product',
                'catalog_category',
                'cms_category',
                'cms_service_category',
                'cms_project_category',
            ])
            ->get();

        foreach ($translations as $translation) {
            $path = match ($translation->resource_type) {
                'cms_post' => FrontendRouteUrl::postPath($translation->slug),
                'cms_service' => FrontendRouteUrl::servicePath($translation->slug),
                'cms_project' => FrontendRouteUrl::projectPath($translation->slug),
                'catalog_product' => FrontendRouteUrl::productPath($translation->slug, $translation->locale),
                'catalog_category' => FrontendRouteUrl::categoryPath($translation->slug, $translation->locale),
                'cms_category' => FrontendRouteUrl::blogCategoryPath($translation->slug),
                'cms_service_category' => FrontendRouteUrl::serviceCategoryPath($translation->slug),
                'cms_project_category' => FrontendRouteUrl::projectCategoryPath($translation->slug),
            };
            $routeName = match ($translation->resource_type) {
                'cms_post' => 'site.blog.show',
                'cms_service' => 'site.services.show',
                'cms_project' => 'site.projects.show',
                'catalog_product' => 'site.catalog.product',
                'catalog_category' => 'site.catalog.category',
                'cms_category' => 'site.blog.category',
                'cms_service_category' => 'site.services.category',
                'cms_project_category' => 'site.projects.category',
            };

            DB::table('localized_routes')->updateOrInsert(
                [
                    'website_key' => $translation->website_key,
                    'locale' => $translation->locale,
                    'resource_type' => $translation->resource_type,
                    'resource_id' => $translation->resource_id,
                    'path' => $path,
                ],
                [
                    'route_name' => $routeName,
                    'is_canonical' => true,
                    'is_published' => true,
                    'redirect_to' => null,
                    'metadata' => json_encode(['slug' => $translation->slug], JSON_UNESCAPED_UNICODE),
                    'published_at' => $translation->translation_published_at ?? $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(object $row, string $slug): array
    {
        return [
            'slug' => $slug,
            'title' => $row->title,
            'excerpt' => $row->excerpt,
            'meta_title' => $row->meta_title,
            'meta_description' => $row->meta_description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(object $row): array
    {
        $content = json_decode((string) ($row->content ?? '{}'), true);

        return [
            'title' => $row->title,
            'subtitle' => $row->subtitle,
            'description' => $row->description,
            'button_label' => $row->button_label,
            'content' => is_array($content) ? $content : [],
        ];
    }
};
