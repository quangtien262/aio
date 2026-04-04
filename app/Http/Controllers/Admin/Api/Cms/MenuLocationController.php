<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CmsMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuLocationController
{
    public function index(CmsMenuLocationRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->all(),
        ]);
    }

    public function store(Request $request, CmsMenuLocationRegistry $registry): JsonResponse
    {
        $validated = $this->validatePayload($request, $registry);
        $locations = $registry->all();
        $locations[] = [
            'label' => $validated['label'],
            'value' => $validated['value'],
        ];

        return response()->json([
            'message' => 'Đã tạo vị trí menu.',
            'data' => $registry->save($locations),
        ], 201);
    }

    public function update(Request $request, CmsMenuLocationRegistry $registry, string $location): JsonResponse
    {
        $current = collect($registry->all())->firstWhere('value', $location);

        if (! $current) {
            abort(404);
        }

        $validated = $this->validatePayload($request, $registry, $location);

        if ($validated['value'] !== $location && CmsMenu::query()->where('location', $location)->exists()) {
            throw ValidationException::withMessages([
                'value' => ['Không thể đổi mã vị trí vì đã có menu đang dùng vị trí này.'],
            ]);
        }

        $locations = collect($registry->all())
            ->map(function (array $item) use ($location, $validated): array {
                if ($item['value'] !== $location) {
                    return $item;
                }

                return [
                    'label' => $validated['label'],
                    'value' => $validated['value'],
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'message' => 'Đã cập nhật vị trí menu.',
            'data' => $registry->save($locations),
        ]);
    }

    public function destroy(CmsMenuLocationRegistry $registry, string $location): JsonResponse
    {
        if (CmsMenu::query()->where('location', $location)->exists()) {
            throw ValidationException::withMessages([
                'location' => ['Không thể xóa vị trí đang được menu sử dụng.'],
            ]);
        }

        $locations = collect($registry->all())
            ->reject(fn (array $item): bool => $item['value'] === $location)
            ->values()
            ->all();

        return response()->json([
            'message' => 'Đã xóa vị trí menu.',
            'data' => $registry->save($locations),
        ]);
    }

    private function validatePayload(Request $request, CmsMenuLocationRegistry $registry, ?string $ignoreValue = null): array
    {
        $existingValues = collect($registry->all())
            ->pluck('value')
            ->reject(fn (string $value): bool => $value === $ignoreValue)
            ->all();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255', Rule::notIn($existingValues)],
        ]);

        $validated['value'] = Str::slug($validated['value'] ?: $validated['label']);

        if ($validated['value'] === '') {
            throw ValidationException::withMessages([
                'value' => ['Mã vị trí không hợp lệ.'],
            ]);
        }

        if (in_array($validated['value'], $existingValues, true)) {
            throw ValidationException::withMessages([
                'value' => ['Mã vị trí đã tồn tại.'],
            ]);
        }

        return $validated;
    }
}
