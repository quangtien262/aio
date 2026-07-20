<?php

namespace App\Models\Concerns;

use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Builder;

trait HasWebsiteScope
{
    protected static function bootHasWebsiteScope(): void
    {
        if (static::usesCurrentWebsiteGlobalScope()) {
            static::addGlobalScope('current_website', function (Builder $builder): void {
                if (! app()->bound(SiteContext::class)) {
                    return;
                }

                $websiteKey = app(SiteContext::class)->websiteKey();

                if ($websiteKey === '') {
                    return;
                }

                $builder->where($builder->getModel()->qualifyColumn('website_key'), $websiteKey);
            });
        }

        static::creating(function ($model): void {
            if (! app()->bound(SiteContext::class)) {
                return;
            }

            if (blank($model->website_key ?? null)) {
                $model->website_key = app(SiteContext::class)->websiteKey();
            }
        });
    }

    public function scopeForWebsite(Builder $query, ?string $websiteKey): Builder
    {
        $websiteKey = trim((string) $websiteKey);

        if ($websiteKey === '') {
            return $query;
        }

        return $query
            ->withoutGlobalScope('current_website')
            ->where($query->getModel()->qualifyColumn('website_key'), $websiteKey);
    }

    protected static function usesCurrentWebsiteGlobalScope(): bool
    {
        if (property_exists(static::class, 'usesCurrentWebsiteGlobalScope')) {
            return (bool) static::$usesCurrentWebsiteGlobalScope;
        }

        return true;
    }
}
