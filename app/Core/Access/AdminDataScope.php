<?php

namespace App\Core\Access;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;

class AdminDataScope
{
    /**
     * @param  array<string, string>  $columnMap
     */
    public function apply(Builder $query, Admin $admin, array $columnMap = []): Builder
    {
        $scopeMatrix = $admin->scopeMatrix();
        $resolvedMap = array_merge([
            'tenant' => 'tenant_key',
            'owner' => 'owner_key',
            'website' => 'website_key',
        ], $columnMap);

        foreach ($resolvedMap as $scopeType => $column) {
            $scopeValues = array_values(array_filter($scopeMatrix[$scopeType] ?? []));

            if ($scopeValues === []) {
                continue;
            }

            $query->whereIn($query->qualifyColumn($column), $scopeValues);
        }

        return $query;
    }
}
