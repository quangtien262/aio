<?php

namespace App\Support\Localization;

use App\Core\Cms\CmsMenuLocalization;
use App\Enums\TranslationStatus;
use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Models\LocalizedRoute;
use App\Support\FrontendRouteUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LocalizedContentRepository
{
    /**
     * @var array<class-string<Model>, string>|null
     */
    private ?array $modelTypes = null;

    public function __construct(
        private readonly LocaleContext $localeContext,
        private readonly LocalizationRollout $rollout,
        private readonly TranslationWorkflowManager $workflow,
        private readonly LocalizedRouteRegistry $routeRegistry,
        private readonly CmsMenuLocalization $menuLocalization,
    ) {}

    public function textByKey(
        string $websiteKey,
        string $locale,
        string $key,
    ): ?string {
        $parsed = $this->parseKey($key);

        if ($parsed === null) {
            return null;
        }

        [$resourceType, $resourceId, $field] = $parsed;

        if (! $this->rollout->usesNewReader($resourceType, $websiteKey)) {
            return null;
        }

        foreach ($this->localeContext->fallbackChain($locale, $websiteKey) as $candidate) {
            $translation = $this->translation(
                $websiteKey,
                $resourceType,
                $resourceId,
                $candidate,
                true,
            );
            $value = data_get($translation?->payload, $field);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    public function localize(
        Model $model,
        string $resourceType,
        string $locale,
        ?string $websiteKey = null,
        bool $publishedOnly = true,
        ?string $themeKey = null,
    ): Model {
        $websiteKey = trim((string) ($websiteKey ?: $model->getAttribute('website_key') ?: 'website-main'));

        if (! $this->rollout->usesNewReader(
            $resourceType,
            $websiteKey,
            $themeKey,
        )) {
            return clone $model;
        }

        $translation = null;

        foreach ($this->localeContext->fallbackChain($locale, $websiteKey) as $candidate) {
            $translation = $this->translation(
                $websiteKey,
                $resourceType,
                (string) $model->getKey(),
                $candidate,
                $publishedOnly,
            );

            if ($translation !== null) {
                break;
            }
        }

        $localized = clone $model;

        if ($translation === null) {
            return $localized;
        }

        if ($resourceType === 'cms_menu' && $model instanceof CmsMenu) {
            $localized->setAttribute(
                'items',
                $this->menuLocalization->localizedItems(
                    is_array($model->items) ? $model->items : [],
                    (array) ($translation->payload ?? []),
                ),
            );
        } else {
            foreach ((array) $translation->payload as $field => $value) {
                $localized->setAttribute((string) $field, $value);
            }
        }

        $localized->setAttribute('resolved_locale', $translation->locale);
        $localized->setAttribute('translation_status', $translation->translation_status);
        $localized->setRelation('currentContentTranslation', $translation);

        return $localized;
    }

    public function localizedSlug(
        Model $model,
        string $resourceType,
        string $locale,
        ?string $websiteKey = null,
    ): string {
        $localized = $this->localize(
            $model,
            $resourceType,
            $locale,
            $websiteKey,
        );
        $definition = $this->definition($resourceType);
        $slugField = (string) ($definition['slug_field'] ?? 'slug');

        return (string) (
            $localized->getAttribute($slugField)
            ?: $model->getAttribute($slugField)
            ?: $model->getKey()
        );
    }

    public function publicCanonicalPath(
        Model $model,
        string $resourceType,
        string $locale,
        ?string $websiteKey = null,
    ): ?string {
        $websiteKey = trim((string) ($websiteKey ?: $model->getAttribute('website_key') ?: 'website-main'));
        $locale = LocaleCode::tryNormalize($locale);

        if ($locale === null || ! $this->localeContext->isPublic($locale, $websiteKey)) {
            return null;
        }

        if ($this->translation(
            $websiteKey,
            $resourceType,
            (string) $model->getKey(),
            $locale,
            true,
        ) === null) {
            return null;
        }

        return $this->routeRegistry->canonicalPaths(
            $resourceType,
            (string) $model->getKey(),
            [$locale],
            $websiteKey,
        )[$locale] ?? null;
    }

    public function findPublishedBySlug(
        string $resourceType,
        string $websiteKey,
        string $locale,
        string $slug,
    ): ?Model {
        $definition = $this->definition($resourceType);
        $modelClass = $definition['model'] ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return null;
        }

        $translation = ContentTranslation::query()
            ->forWebsite($websiteKey)
            ->publishedTranslation()
            ->where('resource_type', $resourceType)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if ($translation === null || ! $this->hasCurrentSourceRevision($translation)) {
            return null;
        }

        /** @var Model|null $model */
        $model = $modelClass::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $websiteKey)
            ->find($translation->resource_id);

        return $model ? $this->localize(
            $model,
            $resourceType,
            $locale,
            $websiteKey,
        ) : null;
    }

    /**
     * @return array{model: Model, resolved_locale: string, used_fallback: bool, redirect_to: ?string}|null
     */
    public function resolvePublishedBySlug(
        string $resourceType,
        string $websiteKey,
        string $locale,
        string $slug,
    ): ?array {
        $definition = $this->definition($resourceType);
        $modelClass = $definition['model'] ?? null;
        $slugField = $definition['slug_field'] ?? null;

        if (
            ! is_string($modelClass)
            || ! class_exists($modelClass)
            || ! is_string($slugField)
        ) {
            return null;
        }

        $locale = $this->localeContext->resolvePublic($locale, $websiteKey);
        $routePath = $this->routePath($resourceType, $slug, $locale);

        if ($routePath !== null) {
            $registered = $this->routeRegistry->resolvePublic(
                $locale,
                $routePath,
                $websiteKey,
            );

            if (
                $registered !== null
                && $registered->resource_type === $resourceType
                && $this->translation(
                    $websiteKey,
                    $resourceType,
                    (string) $registered->resource_id,
                    $locale,
                    true,
                ) !== null
            ) {
                /** @var Model|null $registeredModel */
                $registeredModel = $modelClass::query()
                    ->withoutGlobalScope('current_website')
                    ->where('website_key', $websiteKey)
                    ->find($registered->resource_id);

                if (
                    $registeredModel !== null
                    && $this->modelIsPublished($registeredModel, $definition)
                ) {
                    return [
                        'model' => $this->localize(
                            $registeredModel,
                            $resourceType,
                            $locale,
                            $websiteKey,
                        ),
                        'resolved_locale' => $locale,
                        'used_fallback' => false,
                        'redirect_to' => $registered->redirect_to,
                    ];
                }
            }
        }

        foreach ($this->localeContext->fallbackChain($locale, $websiteKey) as $candidate) {
            if (! $this->localeContext->isPublic($candidate, $websiteKey)) {
                continue;
            }

            $localized = $this->findPublishedBySlug(
                $resourceType,
                $websiteKey,
                $candidate,
                $slug,
            );

            if ($localized !== null && $this->modelIsPublished($localized, $definition)) {
                return [
                    'model' => $localized,
                    'resolved_locale' => $candidate,
                    'used_fallback' => $candidate !== $locale,
                    'redirect_to' => null,
                ];
            }
        }

        /** @var Model|null $legacy */
        $legacy = $modelClass::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $websiteKey)
            ->where($slugField, $slug)
            ->first();

        if ($legacy === null || ! $this->modelIsPublished($legacy, $definition)) {
            return null;
        }

        $sourceLocale = $this->localeContext->sourceLocale();

        return [
            'model' => $this->localize(
                $legacy,
                $resourceType,
                $sourceLocale,
                $websiteKey,
            ),
            'resolved_locale' => $sourceLocale,
            'used_fallback' => $sourceLocale !== $locale,
            'redirect_to' => null,
        ];
    }

    /**
     * @return Collection<int, ContentTranslation>
     */
    public function publicTranslations(
        string $resourceType,
        string|int $resourceId,
        string $websiteKey,
    ): Collection {
        if (! Schema::hasTable('content_translations')) {
            return new Collection;
        }

        return ContentTranslation::query()
            ->forWebsite($websiteKey)
            ->publishedTranslation()
            ->where('resource_type', $resourceType)
            ->where('resource_id', (string) $resourceId)
            ->whereIn('locale', $this->localeContext->publicLocales($websiteKey))
            ->orderBy('locale')
            ->get()
            ->filter(fn (ContentTranslation $translation): bool => $this->hasCurrentSourceRevision($translation))
            ->values();
    }

    public function syncLegacyModel(Model $model): ?ContentTranslation
    {
        if (
            ! $this->rollout->dualWriteEnabled()
            || ! Schema::hasTable('content_translations')
            || ! $model->exists
        ) {
            return null;
        }

        $resourceType = $this->resourceTypeForModel($model);

        if ($resourceType === null) {
            return null;
        }

        $definition = $this->definition($resourceType);
        $payload = $this->payloadFromModel($model, $definition);
        $status = $this->modelIsPublished($model, $definition)
            ? TranslationStatus::Published
            : TranslationStatus::Draft;
        $revision = TranslationRevision::fingerprint($payload);
        $websiteKey = (string) ($model->getAttribute('website_key') ?: 'website-main');
        $sourceLocale = $this->localeContext->sourceLocale();
        $translation = ContentTranslation::query()
            ->withoutGlobalScope('current_website')
            ->firstOrNew([
                'website_key' => $websiteKey,
                'resource_type' => $resourceType,
                'resource_id' => (string) $model->getKey(),
                'locale' => $sourceLocale,
            ]);
        $previousRevision = (string) (
            $translation->translation_revision
            ?: $translation->source_revision
        );

        $translation->forceFill([
            'slug' => isset($definition['slug_field'])
                ? $model->getAttribute($definition['slug_field'])
                : null,
            'payload' => $payload,
            'translation_status' => $status,
            'source_revision' => $revision,
            'translation_revision' => $revision,
            'is_machine_translated' => false,
            'translation_meta' => ['source' => 'legacy_dual_write'],
            'translated_at' => now(),
            'reviewed_at' => $status === TranslationStatus::Published ? now() : null,
            'translation_published_at' => $status === TranslationStatus::Published
                ? ($model->getAttribute('publish_at')
                    ?? $model->getAttribute('published_at')
                    ?? now())
                : null,
        ]);
        $this->removeOrphanedSlugConflict($translation, $model);
        $translation->save();

        $translation->refresh();
        $this->syncRoute($translation);

        if ($previousRevision !== '' && $previousRevision !== $revision) {
            ContentTranslation::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $websiteKey)
                ->where('resource_type', $resourceType)
                ->where('resource_id', (string) $model->getKey())
                ->where('locale', '!=', $sourceLocale)
                ->get()
                ->each(function (ContentTranslation $localized) use ($payload): void {
                    if ($this->workflow->markOutdatedWhenSourceChanges($localized, $payload)) {
                        $localized->refresh();
                        $this->syncRoute($localized);
                    }
                });
        }

        return $translation;
    }

    public function deleteTranslationsForModel(Model $model): void
    {
        $resourceType = $this->resourceTypeForModel($model);

        if ($resourceType === null) {
            return;
        }

        $websiteKey = (string) ($model->getAttribute('website_key') ?: 'website-main');
        $resourceId = (string) $model->getKey();

        if (Schema::hasTable('localized_routes')) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $websiteKey)
                ->where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->delete();
        }

        if (Schema::hasTable('content_translations')) {
            ContentTranslation::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $websiteKey)
                ->where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->delete();
        }
    }

    public function savePublishedFieldByKey(
        string $websiteKey,
        string $locale,
        string $key,
        mixed $value,
    ): bool {
        $parsed = $this->parseKey($key);

        if ($parsed === null || ! Schema::hasTable('content_translations')) {
            return false;
        }

        [$resourceType, $resourceId, $field] = $parsed;
        $definition = $this->definition($resourceType);
        $locale = $this->localeContext->resolveEditable($locale, $websiteKey);
        $source = $this->translation(
            $websiteKey,
            $resourceType,
            $resourceId,
            $this->localeContext->sourceLocale(),
            false,
        );
        $translation = $this->translation(
            $websiteKey,
            $resourceType,
            $resourceId,
            $locale,
            false,
        ) ?? new ContentTranslation([
            'website_key' => $websiteKey,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'locale' => $locale,
        ]);
        $payload = (array) (
            $translation->payload
            ?? $source?->payload
            ?? []
        );
        data_set($payload, $field, $value);

        $slugField = $definition['slug_field'] ?? null;

        if (is_string($slugField)) {
            $translation->slug = trim((string) (
                $field === $slugField
                    ? $value
                    : ($translation->slug ?: data_get($payload, $slugField))
            )) ?: null;
            $this->guardSlugUnique($translation);
        }

        $translation = $this->workflow->saveDraft(
            $translation,
            ['payload' => $payload, 'slug' => $translation->slug],
            (string) ($source?->translation_revision
                ?? TranslationRevision::fingerprint((array) ($source?->payload ?? []))),
            false,
            ['editor' => 'theme.content-translations', 'key' => $key],
        );
        $translation = $this->workflow->transition(
            $translation,
            TranslationStatus::Ready,
        );
        /** @var ContentTranslation $translation */
        $translation = $this->workflow->transition($translation, TranslationStatus::Published);
        $this->syncRoute($translation);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveDraftPayload(
        string $websiteKey,
        string $resourceType,
        string $resourceId,
        string $locale,
        array $payload,
        bool $machineTranslated = false,
        bool $replacePayload = false,
    ): ContentTranslation {
        $definition = $this->definition($resourceType);

        if ($definition === []) {
            throw ValidationException::withMessages([
                'resource_type' => 'Loại nội dung không hỗ trợ đa ngôn ngữ.',
            ]);
        }

        $locale = $this->localeContext->resolveEditable($locale, $websiteKey);
        $allowedFields = (array) ($definition['fields'] ?? []);
        $payload = collect($payload)->only($allowedFields)->all();
        $translation = $this->translation(
            $websiteKey,
            $resourceType,
            $resourceId,
            $locale,
            false,
        ) ?? new ContentTranslation([
            'website_key' => $websiteKey,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'locale' => $locale,
        ]);
        $mergedPayload = $replacePayload
            ? $payload
            : array_replace((array) ($translation->payload ?? []), $payload);
        $slugField = $definition['slug_field'] ?? null;

        if (is_string($slugField) && array_key_exists($slugField, $mergedPayload)) {
            $translation->slug = trim((string) $mergedPayload[$slugField]) ?: null;
            $this->guardSlugUnique($translation);
        }

        $source = $this->translation(
            $websiteKey,
            $resourceType,
            $resourceId,
            $this->localeContext->sourceLocale(),
            false,
        );

        /** @var ContentTranslation $translation */
        $translation = $this->workflow->saveDraft(
            $translation,
            ['payload' => $mergedPayload, 'slug' => $translation->slug],
            (string) ($source?->translation_revision
                ?? TranslationRevision::fingerprint((array) ($source?->payload ?? []))),
            $machineTranslated,
            ['editor' => 'cms.localized-content'],
        );
        $this->syncRoute($translation);

        return $translation;
    }

    public function transition(
        ContentTranslation $translation,
        TranslationStatus $target,
    ): ContentTranslation {
        if ($target === TranslationStatus::Published) {
            $definition = $this->definition($translation->resource_type);
            $labelField = (string) ($definition['label_field'] ?? '');

            if ($labelField !== '' && blank(data_get($translation->payload, $labelField))) {
                throw ValidationException::withMessages([
                    $labelField => 'Nội dung chính là bắt buộc trước khi xuất bản.',
                ]);
            }

            if ($translation->resource_type === 'cms_menu') {
                $menu = CmsMenu::query()
                    ->withoutGlobalScopes()
                    ->where('website_key', $translation->website_key)
                    ->findOrFail($translation->resource_id);

                $this->menuLocalization->assertPublishable(
                    is_array($menu->items) ? $menu->items : [],
                    (array) ($translation->payload ?? []),
                );
            }

            if (! $this->localeContext->isPublic($translation->locale, $translation->website_key)) {
                throw ValidationException::withMessages([
                    'locale' => 'Ngôn ngữ phải được bật công khai trước khi xuất bản nội dung.',
                ]);
            }
        }

        /** @var ContentTranslation $updated */
        $updated = $this->workflow->transition($translation, $target);
        $this->syncRoute($updated);

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ContentTranslation $translation): array
    {
        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);

        return [
            'id' => $translation->id,
            'website_key' => $translation->website_key,
            'resource_type' => $translation->resource_type,
            'resource_id' => $translation->resource_id,
            'locale' => $translation->locale,
            'slug' => $translation->slug,
            'payload' => $translation->payload ?? [],
            'translation_status' => $status->value,
            'allowed_transitions' => collect($status->allowedTransitions())
                ->map(fn (TranslationStatus $target): string => $target->value)
                ->all(),
            'is_machine_translated' => (bool) $translation->is_machine_translated,
            'translated_at' => $translation->translated_at?->toAtomString(),
            'reviewed_at' => $translation->reviewed_at?->toAtomString(),
            'translation_published_at' => $translation->translation_published_at?->toAtomString(),
        ];
    }

    public function clearFieldByKey(
        string $websiteKey,
        string $locale,
        string $key,
    ): bool {
        $parsed = $this->parseKey($key);

        if ($parsed === null || $locale === $this->localeContext->sourceLocale()) {
            return false;
        }

        [$resourceType, $resourceId, $field] = $parsed;
        $translation = $this->translation(
            $websiteKey,
            $resourceType,
            $resourceId,
            $locale,
            false,
        );

        if ($translation === null) {
            return false;
        }

        $payload = (array) $translation->payload;
        data_forget($payload, $field);

        if ($payload === []) {
            $translation->delete();

            return true;
        }

        if ($field === ($this->definition($resourceType)['slug_field'] ?? null)) {
            $translation->slug = null;
        }

        $source = $this->translation(
            $websiteKey,
            $resourceType,
            $resourceId,
            $this->localeContext->sourceLocale(),
            false,
        );
        $translation = $this->workflow->saveDraft(
            $translation,
            ['payload' => $payload, 'slug' => $translation->slug],
            (string) ($source?->translation_revision
                ?? TranslationRevision::fingerprint((array) ($source?->payload ?? []))),
        );
        $translation = $this->workflow->transition(
            $translation,
            TranslationStatus::Ready,
        );
        /** @var ContentTranslation $translation */
        $translation = $this->workflow->transition($translation, TranslationStatus::Published);
        $this->syncRoute($translation);

        return true;
    }

    public function translation(
        string $websiteKey,
        string $resourceType,
        string $resourceId,
        string $locale,
        bool $publishedOnly = true,
    ): ?ContentTranslation {
        if (! Schema::hasTable('content_translations')) {
            return null;
        }

        $translation = ContentTranslation::query()
            ->forWebsite($websiteKey)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('locale', $locale)
            ->when($publishedOnly, fn ($query) => $query->publishedTranslation())
            ->first();

        if (
            $translation !== null
            && $publishedOnly
            && ! $this->hasCurrentSourceRevision($translation)
        ) {
            return null;
        }

        return $translation;
    }

    private function hasCurrentSourceRevision(ContentTranslation $translation): bool
    {
        if ($translation->locale === $this->localeContext->sourceLocale()) {
            return true;
        }

        $translatedFromRevision = trim((string) $translation->source_revision);

        // Legacy imported rows may not carry revision metadata. Keep serving
        // them until they re-enter the managed translation workflow.
        if ($translatedFromRevision === '') {
            return true;
        }

        $source = ContentTranslation::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $translation->website_key)
            ->where('resource_type', $translation->resource_type)
            ->where('resource_id', $translation->resource_id)
            ->where('locale', $this->localeContext->sourceLocale())
            ->first(['source_revision', 'translation_revision']);
        $currentSourceRevision = trim((string) (
            $source?->translation_revision ?: $source?->source_revision
        ));

        return $currentSourceRevision === ''
            || hash_equals($currentSourceRevision, $translatedFromRevision);
    }

    /**
     * @return array{0:string,1:string,2:string}|null
     */
    public function parseKey(string $key): ?array
    {
        if (! preg_match('/^([a-z_]+)\.(\d+)\.(.+)$/', $key, $matches)) {
            return null;
        }

        if (! array_key_exists($matches[1], $this->definitions())) {
            return null;
        }

        return [$matches[1], $matches[2], $matches[3]];
    }

    private function guardSlugUnique(ContentTranslation $translation): void
    {
        if (blank($translation->slug)) {
            return;
        }

        $exists = ContentTranslation::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $translation->website_key)
            ->where('resource_type', $translation->resource_type)
            ->where('locale', $translation->locale)
            ->where('slug', $translation->slug)
            ->when(
                $translation->exists,
                fn ($query) => $query->whereKeyNot($translation->getKey()),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => 'Slug đã được dùng trong cùng loại nội dung và ngôn ngữ.',
            ]);
        }
    }

    private function removeOrphanedSlugConflict(
        ContentTranslation $translation,
        Model $sourceModel,
    ): void {
        if (blank($translation->slug)) {
            return;
        }

        $conflict = ContentTranslation::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $translation->website_key)
            ->where('resource_type', $translation->resource_type)
            ->where('locale', $translation->locale)
            ->where('slug', $translation->slug)
            ->where('resource_id', '!=', $translation->resource_id)
            ->first();

        if ($conflict === null) {
            return;
        }

        $conflictingSource = $sourceModel::query()
            ->withoutGlobalScopes()
            ->whereKey($conflict->resource_id)
            ->first();
        $slugField = (string) (
            $this->definition($translation->resource_type)['slug_field']
            ?? 'slug'
        );
        $stillOwnsSlug = $conflictingSource !== null
            && (string) $conflictingSource->getAttribute($slugField) === (string) $conflict->slug;

        if ($stillOwnsSlug) {
            return;
        }

        if (Schema::hasTable('localized_routes')) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $conflict->website_key)
                ->where('resource_type', $conflict->resource_type)
                ->where('resource_id', $conflict->resource_id)
                ->delete();
        }

        $conflict->delete();
    }

    private function resourceTypeForModel(Model $model): ?string
    {
        $this->modelTypes ??= collect($this->definitions())
            ->mapWithKeys(fn (array $definition, string $type): array => [
                $definition['model'] => $type,
            ])
            ->all();

        return $this->modelTypes[$model::class] ?? null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function payloadFromModel(Model $model, array $definition): array
    {
        return collect((array) ($definition['fields'] ?? []))
            ->mapWithKeys(fn (string $field): array => [
                $field => $model->getAttribute($field),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function modelIsPublished(Model $model, array $definition): bool
    {
        if (isset($definition['publication_field'])) {
            return (string) $model->getAttribute($definition['publication_field']) === 'published';
        }

        if (isset($definition['active_field'])) {
            return (bool) $model->getAttribute($definition['active_field']);
        }

        return true;
    }

    private function syncRoute(ContentTranslation $translation): void
    {
        $path = filled($translation->slug)
            ? $this->routePath(
                $translation->resource_type,
                (string) $translation->slug,
                $translation->locale,
            )
            : null;

        if ($path === null) {
            return;
        }

        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);
        $isPublished = $status === TranslationStatus::Published;

        if (! $isPublished) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $translation->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', $translation->resource_type)
                ->where('resource_id', $translation->resource_id)
                ->update([
                    'is_published' => false,
                    'published_at' => null,
                ]);
        }

        $route = $this->routeRegistry->register(
            $translation->locale,
            $translation->resource_type,
            $translation->resource_id,
            $path,
            [
                'route_name' => $this->routeName($translation->resource_type),
                'is_canonical' => true,
                'is_published' => $isPublished,
                'metadata' => ['slug' => $translation->slug],
                'published_at' => $translation->translation_published_at,
            ],
            $translation->website_key,
        );

        if ($isPublished) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $translation->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', $translation->resource_type)
                ->where('resource_id', $translation->resource_id)
                ->whereKeyNot($route->getKey())
                ->update([
                    'is_canonical' => false,
                    'is_published' => true,
                    'redirect_to' => $path,
                ]);
        }
    }

    private function routePath(
        string $resourceType,
        string $slug,
        ?string $locale = null,
    ): ?string {
        return match ($resourceType) {
            'cms_post' => FrontendRouteUrl::postPath($slug),
            'cms_service' => FrontendRouteUrl::servicePath($slug),
            'cms_project' => FrontendRouteUrl::projectPath($slug),
            'catalog_product' => FrontendRouteUrl::productPath($slug, $locale),
            'catalog_category' => FrontendRouteUrl::categoryPath($slug, $locale),
            'cms_category' => FrontendRouteUrl::blogCategoryPath($slug),
            'cms_service_category' => FrontendRouteUrl::serviceCategoryPath($slug),
            'cms_project_category' => FrontendRouteUrl::projectCategoryPath($slug),
            'real_estate_listing' => FrontendRouteUrl::realEstatePath().'/'.rawurlencode($slug),
            default => null,
        };
    }

    private function routeName(string $resourceType): ?string
    {
        return match ($resourceType) {
            'cms_post' => 'site.blog.show',
            'cms_service' => 'site.services.show',
            'cms_project' => 'site.projects.show',
            'catalog_product' => 'site.catalog.product',
            'catalog_category' => 'site.catalog.category',
            'cms_category' => 'site.blog.category',
            'cms_service_category' => 'site.services.category',
            'cms_project_category' => 'site.projects.category',
            'real_estate_listing' => 'site.real-estate.show',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $resourceType): array
    {
        return (array) ($this->definitions()[$resourceType] ?? []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return (array) config('localized-content.resources', []);
    }
}
