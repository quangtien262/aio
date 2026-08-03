<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLinkRegistry;
use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CmsMenu;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuManagementController
{
    public function __construct(
        private readonly CmsMenuLinkRegistry $linkRegistry,
    ) {}

    public function store(Request $request, CmsMenuLocationRegistry $locationRegistry): JsonResponse
    {
        $validated = $this->validatePayload(
            $request,
            $locationRegistry,
            $this->linkRegistry,
        );

        $menu = CmsMenu::query()->create($validated);

        return response()->json(['message' => 'Đã tạo menu CMS.', 'data' => $this->serialize($menu)], 201);
    }

    public function update(
        Request $request,
        CmsMenuLocationRegistry $locationRegistry,
        int $menu,
    ): JsonResponse {
        $record = CmsMenu::query()->findOrFail($menu);
        $validated = $this->validatePayload(
            $request,
            $locationRegistry,
            $this->linkRegistry,
        );
        $record->update($validated);

        return response()->json(['message' => 'Đã cập nhật menu CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function destroy(int $menu): JsonResponse
    {
        $record = CmsMenu::query()->findOrFail($menu);
        $record->delete();

        return response()->json(['message' => 'Đã xóa menu CMS.']);
    }

    private function validatePayload(
        Request $request,
        CmsMenuLocationRegistry $locationRegistry,
        CmsMenuLinkRegistry $linkRegistry,
    ): array {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', Rule::in($locationRegistry->values())],
            'items' => ['required', 'array'],
        ];

        $this->addMenuItemRules(
            is_array($request->input('items')) ? $request->input('items') : [],
            'items',
            $rules,
            $linkRegistry,
        );

        $validated = $request->validate($rules);
        $validated['items'] = $linkRegistry->normalize($validated['items']);
        $linkRegistry->assertValidTargets(
            $validated['items'],
            app(SiteContext::class)->websiteKey(),
        );

        return $validated;
    }

    /**
     * Validate and retain stable item keys at every menu depth.
     *
     * @param  array<int|string, mixed>  $items
     * @param  array<string, array<int, string>>  $rules
     */
    private function addMenuItemRules(
        array $items,
        string $path,
        array &$rules,
        CmsMenuLinkRegistry $linkRegistry,
    ): void {
        foreach ($items as $index => $item) {
            $itemPath = $path.'.'.$index;
            $rules[$itemPath] = ['array'];
            $rules[$itemPath.'.item_key'] = ['nullable', 'uuid'];
            $rules[$itemPath.'.label'] = ['required', 'string', 'max:255'];
            $rules[$itemPath.'.url'] = ['nullable', 'string', 'max:2000', $this->safeUrlRule()];
            $rules[$itemPath.'.target'] = ['nullable', 'string', 'max:50'];
            $rules[$itemPath.'.link_type'] = [
                'nullable',
                'string',
                'max:50',
                Rule::in($linkRegistry->linkTypes()),
            ];
            $rules[$itemPath.'.link_value'] = ['nullable', 'string', 'max:255'];
            $rules[$itemPath.'.resource_type'] = ['nullable', 'string', 'max:120'];
            $rules[$itemPath.'.resource_id'] = ['nullable', 'string', 'max:64'];
            $rules[$itemPath.'.custom_url'] = ['nullable', 'string', 'max:2000', $this->safeUrlRule()];
            $rules[$itemPath.'.children'] = ['nullable', 'array'];

            if (is_array($item) && is_array($item['children'] ?? null)) {
                $this->addMenuItemRules(
                    $item['children'],
                    $itemPath.'.children',
                    $rules,
                    $linkRegistry,
                );
            }
        }
    }

    private function safeUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
                $fail('URL menu không được sử dụng giao thức thực thi mã.');
            }
        };
    }

    private function serialize(CmsMenu $menu): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'location' => $menu->location,
            'items' => $menu->items ?? [],
        ];
    }
}
