<?php

namespace Modules\Customers\Contracts;

use App\Models\Admin;

/** Consumer authorization must recheck native permissions and live memberships. */
interface CustomerConsumerProvider
{
    public function authorize(Admin $actor, string $websiteKey, string $scopeKey, string $ability): void;

    /** @return array{type:string,key:string} */
    public function bindingScope(string $websiteKey, string $scopeKey): array;

    /** @return iterable<array{source_id:string,profile:array}> */
    public function legacyProfiles(Admin $actor, string $websiteKey, string $scopeKey): iterable;
}
