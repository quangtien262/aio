<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_WEBSITE_KEY = 'website-main';

    private const LOCALE_LENGTH = 35;

    public function up(): void
    {
        $this->widenLocaleColumns();
        $this->addTranslationWorkflowColumns();

        Schema::create('website_locales', function (Blueprint $table): void {
            $table->id();
            $table->string('website_key')->index();
            $table->string('locale', self::LOCALE_LENGTH);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_enabled_for_editing')->default(true)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->string('fallback_locale', self::LOCALE_LENGTH)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('domain')->nullable();
            $table->string('path_prefix', 64)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('date_format', 32)->nullable();
            $table->json('number_format')->nullable();
            $table->timestamps();

            $table->unique(['website_key', 'locale'], 'website_locales_website_locale_unique');
            $table->index(
                ['website_key', 'is_enabled_for_editing', 'is_published'],
                'website_locales_runtime_idx',
            );
            $table->foreign('locale')
                ->references('code')
                ->on('system_locales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('fallback_locale')
                ->references('code')
                ->on('system_locales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('localized_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('website_key')->index();
            $table->string('locale', self::LOCALE_LENGTH)->index();
            $table->string('resource_type', 120);
            $table->string('resource_id', 64);
            $table->string('path', 384);
            $table->string('route_name', 120)->nullable();
            $table->boolean('is_canonical')->default(true)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->string('redirect_to', 384)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['website_key', 'locale', 'path'],
                'localized_routes_website_locale_path_unique',
            );
            $table->index(
                ['website_key', 'locale', 'resource_type', 'resource_id'],
                'localized_routes_resource_idx',
            );
            $table->index(
                ['website_key', 'locale', 'is_published', 'is_canonical'],
                'localized_routes_public_idx',
            );
            $table->foreign('locale')
                ->references('code')
                ->on('system_locales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $this->backfillWebsiteLocales();
    }

    public function down(): void
    {
        Schema::dropIfExists('localized_routes');
        Schema::dropIfExists('website_locales');
        $this->removeTranslationWorkflowColumns();

        // Locale columns intentionally stay widened. Shrinking them during rollback
        // could truncate valid BCP 47 tags created after this migration.
    }

    private function widenLocaleColumns(): void
    {
        $columns = [
            ['system_locales', 'code', false],
            ['theme_translations', 'locale', false],
            ['landing_page_data', 'locale', false],
            ['landing_page_block_data', 'locale', false],
            ['contact_inquiries', 'locale', true],
        ];

        foreach ($columns as [$tableName, $columnName, $nullable]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columnName, $nullable): void {
                $column = $table->string($columnName, self::LOCALE_LENGTH);

                if ($nullable) {
                    $column->nullable();
                }

                $column->change();
            });
        }
    }

    private function addTranslationWorkflowColumns(): void
    {
        foreach (['theme_translations', 'landing_page_data', 'landing_page_block_data'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'translation_status')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                // Existing rows are public today, so the compatibility-safe
                // backfill state is "published".
                $table->string('translation_status', 24)->default('published')->index();
                $table->char('source_revision', 64)->nullable()->index();
                $table->char('translation_revision', 64)->nullable();
                $table->boolean('is_machine_translated')->default(false);
                $table->json('translation_meta')->nullable();
                $table->timestamp('translated_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('translation_published_at')->nullable();
            });
        }
    }

    private function removeTranslationWorkflowColumns(): void
    {
        foreach (['theme_translations', 'landing_page_data', 'landing_page_block_data'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'translation_status')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'translation_status',
                    'source_revision',
                    'translation_revision',
                    'is_machine_translated',
                    'translation_meta',
                    'translated_at',
                    'reviewed_at',
                    'translation_published_at',
                ]);
            });
        }
    }

    private function backfillWebsiteLocales(): void
    {
        $websiteKeys = collect([self::DEFAULT_WEBSITE_KEY]);

        if (Schema::hasTable('site_profiles') && Schema::hasColumn('site_profiles', 'website_key')) {
            $websiteKeys = $websiteKeys->merge(
                DB::table('site_profiles')->whereNotNull('website_key')->pluck('website_key'),
            );
        }

        if (Schema::hasTable('sites') && Schema::hasColumn('sites', 'website_key')) {
            $websiteKeys = $websiteKeys->merge(
                DB::table('sites')->whereNotNull('website_key')->pluck('website_key'),
            );
        }

        $sourceLocale = (string) config('localization.source_locale', 'vi');
        $locales = DB::table('system_locales')
            ->where(function ($query) use ($sourceLocale): void {
                $query
                    ->where('is_default', true)
                    ->orWhere('is_active', true)
                    ->orWhere('code', $sourceLocale);
            })
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        if ($locales->isEmpty()) {
            return;
        }

        $defaultLocale = (string) ($locales->firstWhere('is_default', true)?->code
            ?? $locales->first()?->code
            ?? config('localization.default_locale', 'vi'));
        $configuredFallback = (string) config('localization.fallback_locale', $defaultLocale);
        $fallbackLocale = $locales->contains('code', $configuredFallback)
            ? $configuredFallback
            : $defaultLocale;
        $timestamp = now();

        foreach ($websiteKeys->filter()->map(fn ($key): string => trim((string) $key))->filter()->unique() as $websiteKey) {
            foreach ($locales as $locale) {
                $isDefault = (string) $locale->code === $defaultLocale;
                $isSource = (string) $locale->code === $sourceLocale;

                DB::table('website_locales')->insertOrIgnore([
                    'website_key' => $websiteKey,
                    'locale' => (string) $locale->code,
                    'is_default' => $isDefault,
                    'is_enabled_for_editing' => $isDefault || $isSource || (bool) $locale->is_active,
                    'is_published' => $isDefault || ((bool) $locale->is_active && (bool) $locale->is_published),
                    'fallback_locale' => $isDefault ? null : $fallbackLocale,
                    'sort_order' => (int) $locale->sort_order,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }
};
