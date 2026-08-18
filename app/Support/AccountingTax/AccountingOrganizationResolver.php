<?php

namespace App\Support\AccountingTax;

use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Support\SiteContext;

class AccountingOrganizationResolver
{
    public function resolve(?int $organizationId = null): AcctOrganization
    {
        if ($organizationId !== null) {
            return AcctOrganization::query()->findOrFail($organizationId);
        }

        $websiteKey = app()->bound(SiteContext::class) ? app(SiteContext::class)->websiteKey() : null;

        if ($websiteKey !== null) {
            $mapped = AcctOrganizationWebsite::query()
                ->with('organization')
                ->where('website_key', $websiteKey)
                ->first();

            if ($mapped !== null) {
                return $mapped->organization;
            }
        }

        return AcctOrganization::query()->where('is_default', true)->firstOrFail();
    }
}
