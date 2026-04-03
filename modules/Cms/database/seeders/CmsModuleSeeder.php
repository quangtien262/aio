<?php

namespace Modules\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CmsModuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('cms_pages')->updateOrInsert(
            ['slug' => 'home'],
            [
                'title' => 'Trang chủ',
                'status' => 'published',
                'body' => 'Nội dung mặc định cho module CMS.',
                'website_key' => 'website-main',
                'owner_key' => 'owner-system',
                'tenant_key' => 'tenant-a',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
