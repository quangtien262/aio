<?php

namespace Database\Seeders;

use App\Core\Themes\Demo\Nt502DemoContentProvider;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\Site;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Nt502DemoMediaSeeder extends Seeder
{
    public function run(): void
    {
        $websiteKeys = Site::query()->where('theme_key', 'NT502')->where('status', 'active')->pluck('website_key')
            ->merge(SiteProfile::query()->withoutGlobalScope('current_website')->where('active_theme_key', 'NT502')->pluck('website_key'))
            ->filter()->unique();

        foreach ($websiteKeys as $websiteKey) {
            DB::transaction(function () use ($websiteKey): void {
                $records = ThemeDemoRecord::query()->forWebsite($websiteKey)
                    ->where('theme_key', 'NT502')->where('preset_key', 'nt502-dola-furniture')->get();
                $owned = fn (string $model) => $model::query()->forWebsite($websiteKey)
                    ->whereKey($records->where('model_type', $model)->pluck('model_id'));

                foreach (Nt502DemoContentProvider::CATEGORIES as [$name, $image]) {
                    $this->repairImage($owned(CatalogCategory::class)->where('slug', Str::slug('nt502-'.$name))->first(), 'image_url', $image);
                }
                foreach (Nt502DemoContentProvider::PRODUCTS as $index => $product) {
                    $sku = 'NT502-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                    $this->repairImage($owned(CatalogProduct::class)->where('sku', $sku)->first(), 'image_url', $product[4]);
                }
                foreach (Nt502DemoContentProvider::BANNERS as $index => $banner) {
                    $this->repairImage($owned(SiteBanner::class)->where('placement', 'nt502-hero-slider')->where('sort_order', $index)->first(), 'image_url', $banner[2]);
                }
                foreach (Nt502DemoContentProvider::TESTIMONIALS as $index => $testimonial) {
                    $this->repairImage($owned(CmsTestimonial::class)->where('name', $testimonial[0])->first(), 'image_url', '/theme-demo/xd-shared/person-'.($index + 1).'.jpg');
                }
                foreach (Nt502DemoContentProvider::NEWS as [$title, $excerpt, $image]) {
                    $post = $owned(CmsPost::class)->where('slug', Str::slug('nt502-'.$title))->first();
                    if ($post && $media = $owned(CmsMedia::class)->whereKey($post->featured_media_id)->first()) {
                        // Uploaded media is user content; repair only the old URL-based demo cover.
                        if (blank($media->getRawOriginal('file_path'))) {
                            $this->repairImage($media, 'file_url', $image, true);
                        }
                    }
                }

                // Fill the fifth news slot without resetting the website or replacing existing articles.
                [$title, $excerpt, $image] = Nt502DemoContentProvider::NEWS[4];
                $slug = Str::slug('nt502-'.$title);
                $category = $owned(CmsCategory::class)->where('slug', 'nt502-cam-nang-noi-that')->first();
                if (! $category || CmsPost::query()->forWebsite($websiteKey)->where('slug', $slug)->exists()) {
                    return;
                }
                $media = CmsMedia::create([
                    'website_key' => $websiteKey, 'title' => $title, 'alt_text' => $title,
                    'file_path' => '', 'file_url' => $image, 'mime_type' => 'image/webp',
                ]);
                $post = CmsPost::create([
                    'website_key' => $websiteKey, 'category_id' => $category->id, 'featured_media_id' => $media->id,
                    'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'body' => '<p>'.e($excerpt).'</p>',
                    'status' => 'published', 'publish_at' => now()->subDays(5), 'is_highlight' => true,
                ]);
                foreach ([$media, $post] as $record) {
                    ThemeDemoRecord::create([
                        'website_key' => $websiteKey, 'theme_key' => 'NT502', 'preset_key' => 'nt502-dola-furniture',
                        'model_type' => $record::class, 'model_id' => $record->getKey(),
                    ]);
                }
            });
        }
    }

    private function repairImage(?Model $model, string $field, string $image, bool $newsCover = false): void
    {
        if (! $model) {
            return;
        }
        $current = trim((string) $model->getRawOriginal($field));
        $malformed = preg_match('~^https?://images\.unsplash\.com/\d[^/]*~i', $current) === 1;
        $missingLocal = str_starts_with($current, '/theme-demo/') && ! is_file(public_path(parse_url($current, PHP_URL_PATH)));
        $legacyNews = $newsCover && preg_match('~^https?://images\.unsplash\.com/photo-1503387762-592deb58ef4e(?:\?|$)~i', $current) === 1;

        if ($current === '' || $malformed || $missingLocal || $legacyNews) {
            $model->update([$field => $image]);
        }
    }
}
