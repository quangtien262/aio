<?php

namespace App\Http\Controllers\Admin\Api\Cms\Concerns;

use App\Core\Access\AdminDataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

trait InteractsWithScopedCmsRecords
{
    private function applyAdminScope(Builder $query, Request $request, AdminDataScope $adminDataScope): Builder
    {
        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        return $query;
    }

    private function resolveScopedRecord(Request $request, AdminDataScope $adminDataScope, Model $model, int $id): Model
    {
        $query = $model::query();
        $this->applyAdminScope($query, $request, $adminDataScope);

        return $query->findOrFail($id);
    }

    private function ensureScopedPayloadAllowed(Request $request, array $validated): void
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return;
        }

        $scopeMatrix = $admin->scopeMatrix();

        foreach (['website' => 'website_key', 'owner' => 'owner_key', 'tenant' => 'tenant_key'] as $scopeType => $field) {
            $allowedValues = array_values(array_filter($scopeMatrix[$scopeType] ?? []));

            if ($allowedValues === []) {
                continue;
            }

            $value = Arr::get($validated, $field);

            if (! is_string($value) || $value === '' || ! in_array($value, $allowedValues, true)) {
                throw ValidationException::withMessages([
                    $field => ['Giá trị scope nằm ngoài phạm vi admin được cấp.'],
                ]);
            }
        }
    }
}
