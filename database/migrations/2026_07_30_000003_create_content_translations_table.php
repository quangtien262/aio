<?php

use App\Enums\TranslationStatus;
use App\Support\Localization\TranslationRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_translations')) {
            Schema::create('content_translations', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key');
                $table->string('resource_type', 80);
                $table->string('resource_id', 64);
                $table->string('locale', 35);
                $table->string('slug')->nullable();
                $table->json('payload');
                $table->string('translation_status', 40)->default(TranslationStatus::Missing->value);
                $table->string('source_revision', 64)->nullable();
                $table->string('translation_revision', 64)->nullable();
                $table->boolean('is_machine_translated')->default(false);
                $table->json('translation_meta')->nullable();
                $table->timestamp('translated_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('translation_published_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['website_key', 'resource_type', 'resource_id', 'locale'],
                    'content_translations_resource_locale_unique',
                );
                $table->unique(
                    ['website_key', 'resource_type', 'locale', 'slug'],
                    'content_translations_localized_slug_unique',
                );
                $table->index(
                    ['website_key', 'locale', 'translation_status'],
                    'content_translations_public_lookup_idx',
                );
            });
        }

        $this->backfillSourceLocale();
        $this->importExistingContentOverrides();
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }

    private function backfillSourceLocale(): void
    {
        $sourceLocale = (string) config('localization.source_locale', 'vi');
        $timestamp = now();

        foreach ((array) config('localized-content.resources', []) as $resourceType => $definition) {
            $modelClass = $definition['model'] ?? null;

            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            $modelClass::query()
                ->withoutGlobalScopes()
                ->orderBy($model->getKeyName())
                ->chunkById(200, function ($records) use (
                    $resourceType,
                    $definition,
                    $sourceLocale,
                    $timestamp,
                ): void {
                    foreach ($records as $record) {
                        $payload = collect((array) ($definition['fields'] ?? []))
                            ->mapWithKeys(fn (string $field): array => [
                                $field => $record->getAttribute($field),
                            ])
                            ->all();
                        $isPublished = $this->isPublished($record, $definition);
                        $revision = TranslationRevision::fingerprint($payload);
                        $publishedAt = $isPublished
                            ? ($record->getAttribute('publish_at')
                                ?? $record->getAttribute('published_at')
                                ?? $record->getAttribute('updated_at')
                                ?? $timestamp)
                            : null;

                        DB::table('content_translations')->updateOrInsert(
                            [
                                'website_key' => $record->getAttribute('website_key') ?: 'website-main',
                                'resource_type' => $resourceType,
                                'resource_id' => (string) $record->getKey(),
                                'locale' => $sourceLocale,
                            ],
                            [
                                'slug' => isset($definition['slug_field'])
                                    ? $record->getAttribute($definition['slug_field'])
                                    : null,
                                'payload' => json_encode(
                                    $payload,
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                                ),
                                'translation_status' => $isPublished
                                    ? TranslationStatus::Published->value
                                    : TranslationStatus::Draft->value,
                                'source_revision' => $revision,
                                'translation_revision' => $revision,
                                'is_machine_translated' => false,
                                'translation_meta' => json_encode([
                                    'migration' => 'content_translations_v1',
                                    'source_table' => $record->getTable(),
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'translated_at' => $record->getAttribute('updated_at') ?? $timestamp,
                                'reviewed_at' => $isPublished ? ($record->getAttribute('updated_at') ?? $timestamp) : null,
                                'translation_published_at' => $publishedAt,
                                'created_at' => $record->getAttribute('created_at') ?? $timestamp,
                                'updated_at' => $record->getAttribute('updated_at') ?? $timestamp,
                            ],
                        );
                    }
                });
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function isPublished(object $record, array $definition): bool
    {
        if (isset($definition['publication_field'])) {
            return (string) $record->getAttribute($definition['publication_field']) === 'published';
        }

        if (isset($definition['active_field'])) {
            return (bool) $record->getAttribute($definition['active_field']);
        }

        return true;
    }

    private function importExistingContentOverrides(): void
    {
        if (! Schema::hasTable('theme_translations')) {
            return;
        }

        $definitions = (array) config('localized-content.resources', []);

        DB::table('theme_translations')
            ->where('group', 'content')
            ->where('theme_key', 'like', 'site-content:%')
            ->orderBy('id')
            ->chunkById(200, function ($translations) use ($definitions): void {
                foreach ($translations as $translation) {
                    if (! preg_match(
                        '/^([a-z_]+)\.(\d+)\.(.+)$/',
                        (string) $translation->translation_key,
                        $matches,
                    )) {
                        continue;
                    }

                    [, $resourceType, $resourceId, $field] = $matches;
                    $definition = $definitions[$resourceType] ?? null;

                    if (! is_array($definition)) {
                        continue;
                    }

                    $websiteKey = substr(
                        (string) $translation->theme_key,
                        strlen('site-content:'),
                    ) ?: 'website-main';
                    $existing = DB::table('content_translations')
                        ->where('website_key', $websiteKey)
                        ->where('resource_type', $resourceType)
                        ->where('resource_id', $resourceId)
                        ->where('locale', $translation->locale)
                        ->first();
                    $source = DB::table('content_translations')
                        ->where('website_key', $websiteKey)
                        ->where('resource_type', $resourceType)
                        ->where('resource_id', $resourceId)
                        ->where('locale', config('localization.source_locale', 'vi'))
                        ->first();
                    $payload = json_decode((string) ($existing?->payload ?? '{}'), true);
                    $payload = is_array($payload) ? $payload : [];
                    data_set($payload, $field, $translation->value);
                    $status = TranslationStatus::tryFrom(
                        (string) ($translation->translation_status ?? ''),
                    ) ?? TranslationStatus::Published;

                    DB::table('content_translations')->updateOrInsert(
                        [
                            'website_key' => $websiteKey,
                            'resource_type' => $resourceType,
                            'resource_id' => $resourceId,
                            'locale' => $translation->locale,
                        ],
                        [
                            'slug' => $field === ($definition['slug_field'] ?? null)
                                ? $translation->value
                                : $existing?->slug,
                            'payload' => json_encode(
                                $payload,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ),
                            'translation_status' => $status->value,
                            'source_revision' => $source?->translation_revision,
                            'translation_revision' => TranslationRevision::fingerprint($payload),
                            'is_machine_translated' => (bool) ($translation->is_machine_translated ?? false),
                            'translation_meta' => json_encode([
                                'migration' => 'theme_content_override_import',
                                'legacy_theme_translation_id' => $translation->id,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'translated_at' => $translation->translated_at ?? $translation->updated_at,
                            'reviewed_at' => $translation->reviewed_at,
                            'translation_published_at' => $status === TranslationStatus::Published
                                ? ($translation->translation_published_at ?? $translation->updated_at)
                                : null,
                            'created_at' => $existing?->created_at ?? $translation->created_at,
                            'updated_at' => $translation->updated_at,
                        ],
                    );
                }
            });
    }
};
