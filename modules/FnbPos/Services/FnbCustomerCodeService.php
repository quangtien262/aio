<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;

final class FnbCustomerCodeService
{
    public function generate(string $websiteKey, int $customerId): string
    {
        // The database ID keeps automatically allocated base codes distinct.
        // A suffix accommodates existing manually entered or archived codes.
        $base = 'KH'.str_pad((string) $customerId, 6, '0', STR_PAD_LEFT);
        $code = $base;
        $suffix = 0;
        while (DB::table('fnb_customer_profiles')
            ->where('website_key', $websiteKey)
            ->where('id', '<>', $customerId)
            ->where('code', $code)
            ->exists()) {
            $code = $base.'-'.++$suffix;
        }

        return $code;
    }
}
