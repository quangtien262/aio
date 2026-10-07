<?php

namespace Database\Seeders;

use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Nt501ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = json_decode(file_get_contents(resource_path('demo/remaining-themes.json')), true, 512, JSON_THROW_ON_ERROR)['NT501'];
        $websiteKeys = Site::query()->where('theme_key', 'NT501')->pluck('website_key')
            ->merge(SiteProfile::query()->withoutGlobalScope('current_website')->where('active_theme_key', 'NT501')->pluck('website_key'))
            ->filter()->unique();

        DB::transaction(function () use ($definitions, $websiteKeys): void {
            foreach ($websiteKeys as $websiteKey) {
                foreach ($definitions['projects'] as $index => $definition) {
                    $project = CmsProject::query()->forWebsite($websiteKey)->firstOrCreate([
                        'website_key' => $websiteKey,
                        'slug' => Str::slug('NT501-'.$definition['title']),
                    ], [
                        'title' => $definition['title'],
                        'summary' => $definition['summary'],
                        'content' => '<h2>'.e($definition['title']).'</h2><p>'.e($definition['summary']).'</p>',
                        'status' => 'published',
                        'publish_at' => now(),
                        'is_featured' => true,
                        'is_highlight' => true,
                        'sort_order' => $index,
                    ]);

                    // Reuse existing projects without overwriting or taking ownership of manual content.
                    if (! $project->wasRecentlyCreated) {
                        continue;
                    }

                    $image = CmsProjectImage::create([
                        'cms_project_id' => $project->id,
                        'image_url' => $definition['image'],
                        'alt_text' => $definition['title'],
                        'is_featured' => true,
                        'sort_order' => 0,
                    ]);
                    foreach ([$project, $image] as $record) {
                        ThemeDemoRecord::create([
                            'website_key' => $websiteKey,
                            'theme_key' => 'NT501',
                            'preset_key' => $definitions['preset'],
                            'model_type' => $record::class,
                            'model_id' => $record->id,
                        ]);
                    }
                }
            }
        });
    }
}
