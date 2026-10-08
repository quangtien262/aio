<?php

namespace Database\Seeders;

use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Xd0313ContactBlockSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(SiteContext::class);
        $previousSite = $context->site();
        $previousWebsite = $context->websiteKey();

        try {
            foreach (LandingPage::query()->where('theme_key', 'XD0313')->where('is_home', true)->get() as $page) {
                $context->set(null, $page->website_key);
                DB::transaction(function () use ($page): void {
                    $page->refresh();
                    if ($page->blocks()->where('block_type', 'landing_contact')->exists()) {
                        return;
                    }
                    $block = app(LandingPageBuilder::class)->createBlock($page, 'landing_contact');
                    if (! $page->blocks()->where('anchor_id', 'lien-he')->exists()) {
                        $block->update(['anchor_id' => 'lien-he']);
                    }
                });
            }
        } finally {
            $context->set($previousSite, $previousWebsite);
        }
    }
}
