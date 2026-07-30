<?php

use App\Enums\TranslationStatus;
use App\Support\Localization\TranslationRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_translations')) {
            return;
        }

        $sourceLocale = (string) config('localization.source_locale', 'vi');

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
                    $definition,
                    $resourceType,
                    $sourceLocale,
                ): void {
                    foreach ($records as $record) {
                        $payload = collect((array) ($definition['fields'] ?? []))
                            ->mapWithKeys(fn (string $field): array => [
                                $field => $record->getAttribute($field),
                            ])
                            ->all();
                        $revision = TranslationRevision::fingerprint($payload);
                        $websiteKey = (string) (
                            $record->getAttribute('website_key')
                            ?: 'website-main'
                        );
                        $resourceId = (string) $record->getKey();
                        $status = $this->isPublished($record, $definition)
                            ? TranslationStatus::Published
                            : TranslationStatus::Draft;
                        $now = now();

                        DB::table('content_translations')->updateOrInsert(
                            [
                                'website_key' => $websiteKey,
                                'resource_type' => $resourceType,
                                'resource_id' => $resourceId,
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
                                'translation_status' => $status->value,
                                'source_revision' => $revision,
                                'translation_revision' => $revision,
                                'is_machine_translated' => false,
                                'translation_meta' => json_encode([
                                    'migration' => 'content_translations_reconcile_v1',
                                    'source_table' => $record->getTable(),
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'translated_at' => $record->getAttribute('updated_at') ?? $now,
                                'reviewed_at' => $status === TranslationStatus::Published
                                    ? ($record->getAttribute('updated_at') ?? $now)
                                    : null,
                                'translation_published_at' => $status === TranslationStatus::Published
                                    ? ($record->getAttribute('published_at') ?? $now)
                                    : null,
                                'created_at' => $record->getAttribute('created_at') ?? $now,
                                'updated_at' => $now,
                            ],
                        );

                        DB::table('content_translations')
                            ->where('website_key', $websiteKey)
                            ->where('resource_type', $resourceType)
                            ->where('resource_id', $resourceId)
                            ->where('locale', '!=', $sourceLocale)
                            ->where(fn ($query) => $query
                                ->whereNull('source_revision')
                                ->orWhere('source_revision', '!=', $revision))
                            ->whereIn('translation_status', [
                                TranslationStatus::Draft->value,
                                TranslationStatus::MachineDraft->value,
                                TranslationStatus::InReview->value,
                                TranslationStatus::Ready->value,
                                TranslationStatus::Published->value,
                                TranslationStatus::Outdated->value,
                            ])
                            ->update([
                                'translation_status' => TranslationStatus::Outdated->value,
                                'translation_published_at' => null,
                                'updated_at' => $now,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Reconciliation is intentionally non-destructive and is not rolled back.
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
};
