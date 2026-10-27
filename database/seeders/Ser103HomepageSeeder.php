<?php

namespace Database\Seeders;

use App\Core\Themes\Demo\Ser103HomepageDataRepair;
use App\Models\LandingPage;
use Illuminate\Database\Seeder;

class Ser103HomepageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(Ser103ServicesSeeder::class);
        $repair = app(Ser103HomepageDataRepair::class);
        foreach (LandingPage::query()->where('theme_key', 'SER103')->where('is_home', true)->distinct()->pluck('website_key') as $websiteKey) {
            $this->command?->info($websiteKey.': '.$repair->repairForWebsite($websiteKey).' khối mẫu đã chuyển sang CMS.');
        }
    }
}
