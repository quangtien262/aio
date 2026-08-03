<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Cms\CmsMenuLocalization;
use App\Enums\TranslationStatus;
use App\Models\ContentTranslation;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocalizedContentController
{
    public function __construct(
        private readonly LocalizedContentRepository $repository,
        private readonly LocaleContext $localeContext,
        private readonly SiteContext $siteContext,
        private readonly CmsMenuLocalization $menuLocalization,
    ) {}

    public function show(
        Request $request,
        string $resourceType,
        string $resourceId,
    ): JsonResponse {
        $definition = $this->definition($resourceType);
        $this->authorize($request, $definition, 'view');
        $resource = $this->resource($definition, $resourceId);
        $websiteKey = $this->siteContext->websiteKey();
        $translations = ContentTranslation::query()
            ->forWebsite($websiteKey)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('locale')
            ->get()
            ->map(fn (ContentTranslation $translation): array => (
                $this->repository->serialize($translation)
            ))
            ->keyBy('locale');
        $translationTemplate = [];

        if ($resourceType === 'cms_menu') {
            $sourceItems = is_array($resource->items) ? $resource->items : [];
            $sourceLocale = $this->localeContext->sourceLocale();
            $translations = $translations->map(function (
                array $translation,
                string $locale,
            ) use ($sourceItems, $sourceLocale): array {
                $payload = (array) ($translation['payload'] ?? []);
                $items = $locale === $sourceLocale
                    ? $sourceItems
                    : $this->menuLocalization->editableItems(
                        $sourceItems,
                        $payload,
                    );

                $translation['payload'] = ['items' => $items];
                $translation['translation_progress'] = $locale === $sourceLocale
                    ? [
                        'translated' => $this->countMenuItems($sourceItems),
                        'total' => $this->countMenuItems($sourceItems),
                        'percentage' => 100,
                        'complete' => true,
                    ]
                    : $this->menuLocalization->progress($sourceItems, $payload);

                return $translation;
            });
            $translationTemplate = [
                'items' => $this->menuLocalization->editableItems(
                    $sourceItems,
                    [],
                ),
            ];
        }

        return response()->json([
            'data' => [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'fields' => array_values((array) ($definition['fields'] ?? [])),
                'translations' => $translations,
                'locales' => collect($this->localeContext->options($websiteKey))
                    ->filter(fn (array $locale): bool => (bool) $locale['is_enabled_for_editing'])
                    ->values(),
                'source_locale' => $this->localeContext->sourceLocale(),
                'default_locale' => $this->localeContext->defaultLocale($websiteKey),
                'translation_template' => $translationTemplate,
            ],
        ]);
    }

    public function update(
        Request $request,
        string $resourceType,
        string $resourceId,
        string $locale,
    ): JsonResponse {
        $definition = $this->definition($resourceType);
        $this->authorize($request, $definition, 'update');
        $resource = $this->resource($definition, $resourceId);
        $resolvedLocale = $this->localeContext->resolveEditable(
            $locale,
            $this->siteContext->websiteKey(),
        );

        if ($resolvedLocale === $this->localeContext->sourceLocale()) {
            throw ValidationException::withMessages([
                'locale' => 'Ngôn ngữ gốc phải được chỉnh sửa tại màn hình quản lý nội dung chính.',
            ]);
        }

        $rules = ['payload' => ['required', 'array']];

        foreach ((array) ($definition['fields'] ?? []) as $field) {
            $rules["payload.{$field}"] = ['nullable'];
        }

        $rules['publish'] = ['nullable', 'boolean'];
        $rules['is_machine_translated'] = ['nullable', 'boolean'];
        $validated = $request->validate($rules);
        $payload = (array) $validated['payload'];

        if ($resourceType === 'cms_menu') {
            $payload = $this->menuLocalization->storagePayload(
                is_array($resource->items) ? $resource->items : [],
                $payload,
            );
        }

        if ((bool) ($validated['publish'] ?? false)) {
            $this->authorize($request, $definition, 'publish');
        }

        $translation = $this->repository->saveDraftPayload(
            $this->siteContext->websiteKey(),
            $resourceType,
            $resourceId,
            $resolvedLocale,
            $payload,
            (bool) ($validated['is_machine_translated'] ?? false),
        );

        if ((bool) ($validated['publish'] ?? false)) {
            $translation = $this->repository->transition(
                $translation,
                TranslationStatus::Ready,
            );
            $translation = $this->repository->transition(
                $translation,
                TranslationStatus::Published,
            );
        }

        return response()->json([
            'message' => (bool) ($validated['publish'] ?? false)
                ? 'Đã lưu và xuất bản nội dung theo ngôn ngữ.'
                : 'Đã lưu bản nháp nội dung theo ngôn ngữ.',
            'data' => $this->repository->serialize($translation),
        ]);
    }

    public function transition(
        Request $request,
        string $resourceType,
        string $resourceId,
        string $locale,
    ): JsonResponse {
        $definition = $this->definition($resourceType);
        $this->authorize($request, $definition, 'publish');
        $this->resource($definition, $resourceId);
        $resolvedLocale = $this->localeContext->resolveEditable(
            $locale,
            $this->siteContext->websiteKey(),
        );

        if ($resolvedLocale === $this->localeContext->sourceLocale()) {
            throw ValidationException::withMessages([
                'locale' => 'Trạng thái ngôn ngữ gốc được quản lý từ bản ghi nội dung chính.',
            ]);
        }

        $validated = $request->validate([
            'translation_status' => [
                'required',
                'string',
                Rule::enum(TranslationStatus::class),
            ],
        ]);
        $translation = ContentTranslation::query()
            ->forWebsite($this->siteContext->websiteKey())
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('locale', $resolvedLocale)
            ->firstOrFail();
        $translation = $this->repository->transition(
            $translation,
            TranslationStatus::from($validated['translation_status']),
        );

        return response()->json([
            'message' => 'Đã chuyển trạng thái nội dung theo ngôn ngữ.',
            'data' => $this->repository->serialize($translation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $resourceType): array
    {
        $definition = config("localized-content.resources.{$resourceType}");

        abort_unless(is_array($definition), 404);

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function authorize(
        Request $request,
        array $definition,
        string $ability,
    ): void {
        $admin = $request->user('admin');
        $permissions = (array) ($definition["{$ability}_permissions"]
            ?? $definition['update_permissions']
            ?? []);

        abort_unless($admin && collect($permissions)->contains(
            fn (string $permission): bool => $admin->canAccess(
                $permission,
                'website',
                $this->siteContext->websiteKey(),
            ),
        ), 403);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resource(array $definition, string $resourceId): object
    {
        $modelClass = $definition['model'];

        return $modelClass::query()
            ->withoutGlobalScopes()
            ->where('website_key', $this->siteContext->websiteKey())
            ->whereKey($resourceId)
            ->firstOrFail();
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function countMenuItems(array $items): int
    {
        return collect($items)->sum(function (mixed $item): int {
            if (! is_array($item)) {
                return 0;
            }

            return 1 + $this->countMenuItems(
                is_array($item['children'] ?? null)
                    ? $item['children']
                    : [],
            );
        });
    }
}
