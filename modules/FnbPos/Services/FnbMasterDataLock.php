<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Serialize HQ master edits/publications only; POS writes never acquire this lock. */
final class FnbMasterDataLock
{
    public function acquire(string $website): void
    {
        if (! Schema::hasTable('fnb_menu_master_locks')) {
            return;
        }
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('Master data lock requires a transaction.');
        }
        DB::table('fnb_menu_master_locks')->insertOrIgnore(['website_key' => $website]);
        DB::table('fnb_menu_master_locks')->where('website_key', $website)->lockForUpdate()->first();
    }
}
