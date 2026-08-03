<?php

namespace App\Core\Cms;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\LandingPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CmsMenuLinkRegistry
{
    /**
     * A menu node keeps the editor-friendly link_type/link_value pair for
     * backward compatibility and also persists an explicit resource identity.
     *
     * @var array<string, array{resource_type:string,model:class-string<Model>}>
     */
    private const RESOURCE_LINKS = [
        'page' => [
            'resource_type' => 'cms_page',
            'model' => CmsPage::class,
        ],
        'landing-page' => [
            'resource_type' => 'landing_page',
            'model' => LandingPage::class,
        ],
        'product-category' => [
            'resource_type' => 'catalog_category',
            'model' => CatalogCategory::class,
        ],
        'product' => [
            'resource_type' => 'catalog_product',
            'model' => CatalogProduct::class,
        ],
        'post-category' => [
            'resource_type' => 'cms_category',
            'model' => CmsCategory::class,
        ],
        'post' => [
            'resource_type' => 'cms_post',
            'model' => CmsPost::class,
        ],
        'service-category' => [
            'resource_type' => 'cms_service_category',
            'model' => CmsServiceCategory::class,
        ],
        'service' => [
            'resource_type' => 'cms_service',
            'model' => CmsService::class,
        ],
        'project-category' => [
            'resource_type' => 'cms_project_category',
            'model' => CmsProjectCategory::class,
        ],
        'project' => [
            'resource_type' => 'cms_project',
            'model' => CmsProject::class,
        ],
    ];

    /**
     * @var list<string>
     */
    private const SPECIAL_LINK_TYPES = [
        'home',
        'contact',
        'catalog-index',
        'post-index',
        'service-index',
        'project-index',
        'real-estate-index',
    ];

    /**
     * @return list<string>
     */
    public function linkTypes(): array
    {
        return [
            ...self::SPECIAL_LINK_TYPES,
            ...array_keys(self::RESOURCE_LINKS),
            'custom',
        ];
    }

    /**
     * @return list<string>
     */
    public function specialLinkTypes(): array
    {
        return self::SPECIAL_LINK_TYPES;
    }

    public function resourceType(string $linkType): ?string
    {
        return self::RESOURCE_LINKS[$linkType]['resource_type'] ?? null;
    }

    public function linkType(string $resourceType): ?string
    {
        foreach (self::RESOURCE_LINKS as $linkType => $definition) {
            if ($definition['resource_type'] === $resourceType) {
                return $linkType;
            }
        }

        return null;
    }

    /**
     * @return array{resource_type:string,resource_id:string}|null
     */
    public function identity(array $item): ?array
    {
        $resourceType = trim((string) ($item['resource_type'] ?? ''));
        $resourceId = trim((string) ($item['resource_id'] ?? ''));

        if ($resourceType !== '' && $resourceId !== '' && $this->linkType($resourceType) !== null) {
            return [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ];
        }

        $linkType = trim((string) ($item['link_type'] ?? ''));
        $linkValue = trim((string) ($item['link_value'] ?? ''));
        $resourceType = $this->resourceType($linkType);

        if ($resourceType === null || $linkValue === '') {
            return null;
        }

        return [
            'resource_type' => $resourceType,
            'resource_id' => $linkValue,
        ];
    }

    /**
     * Normalize every node without removing legacy URL fields.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    public function normalize(array $items): array
    {
        return collect($items)
            ->values()
            ->map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $identity = $this->identity($item);

                if ($identity !== null) {
                    $item['resource_type'] = $identity['resource_type'];
                    $item['resource_id'] = $identity['resource_id'];
                    $item['link_type'] = $this->linkType($identity['resource_type']);
                    $item['link_value'] = $identity['resource_id'];
                } else {
                    $item['resource_type'] = null;
                    $item['resource_id'] = null;
                }

                if (is_array($item['children'] ?? null)) {
                    $item['children'] = $this->normalize($item['children']);
                }

                return $item;
            })
            ->all();
    }

    /**
     * Reject stale or cross-website resource identities at the Admin boundary.
     *
     * @param  array<int, mixed>  $items
     */
    public function assertValidTargets(
        array $items,
        string $websiteKey,
        string $path = 'items',
    ): void {
        $errors = [];
        $this->collectTargetErrors($items, $websiteKey, $path, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, string>  $errors
     */
    private function collectTargetErrors(
        array $items,
        string $websiteKey,
        string $path,
        array &$errors,
    ): void {
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemPath = "{$path}.{$index}";
            $linkType = trim((string) ($item['link_type'] ?? 'custom'));
            $identity = $this->identity($item);
            $expectsResource = $this->resourceType($linkType) !== null;

            if ($expectsResource && $identity === null) {
                $errors[$itemPath.'.link_value'] = 'Hãy chọn nội dung nội bộ cho mục Menu này.';
            } elseif ($identity !== null && ! $this->targetExists(
                $identity['resource_type'],
                $identity['resource_id'],
                $websiteKey,
            )) {
                $errors[$itemPath.'.link_value'] = 'Nội dung được liên kết không tồn tại trên website hiện tại.';
            }

            if (is_array($item['children'] ?? null)) {
                $this->collectTargetErrors(
                    $item['children'],
                    $websiteKey,
                    $itemPath.'.children',
                    $errors,
                );
            }
        }
    }

    private function targetExists(
        string $resourceType,
        string $resourceId,
        string $websiteKey,
    ): bool {
        $linkType = $this->linkType($resourceType);
        $modelClass = $linkType !== null
            ? (self::RESOURCE_LINKS[$linkType]['model'] ?? null)
            : null;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return false;
        }

        /** @var Model $model */
        $model = new $modelClass;
        $query = $modelClass::query()->withoutGlobalScopes()->whereKey($resourceId);

        if (Schema::hasColumn($model->getTable(), 'website_key')) {
            $query->where('website_key', $websiteKey);
        }

        return $query->exists();
    }
}
