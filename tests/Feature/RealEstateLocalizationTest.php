<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\ModuleInstallation;
use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\WebsiteLocale;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LocalizedContentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealEstateLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_and_landing_sources_use_published_real_estate_translations(): void
    {
        $this->prepareWebsite();
        $type = RealEstatePropertyType::query()->create([
            'website_key' => 'website-main',
            'name' => 'Biệt thự',
            'slug' => 'biet-thu',
            'description' => 'Biệt thự nguồn',
            'is_active' => true,
        ]);
        $listing = RealEstateListing::query()->create([
            'website_key' => 'website-main',
            'property_type_id' => $type->id,
            'title' => 'Biệt thự ven sông',
            'slug' => 'biet-thu-ven-song',
            'summary' => 'Mô tả nguồn',
            'content' => '<p>Nội dung nguồn</p>',
            'publication_status' => 'published',
            'availability_status' => 'available',
            'transaction_type' => 'sale',
            'published_at' => now(),
        ]);

        $repository = app(LocalizedContentRepository::class);
        $typeTranslation = $repository->saveDraftPayload(
            'website-main',
            'real_estate_property_type',
            (string) $type->id,
            'en',
            ['name' => 'Villa', 'slug' => 'villa', 'description' => 'Translated property type'],
        );
        $listingTranslation = $repository->saveDraftPayload(
            'website-main',
            'real_estate_listing',
            (string) $listing->id,
            'en',
            [
                'title' => 'Riverside villa',
                'slug' => 'riverside-villa',
                'summary' => 'Translated listing summary',
                'content' => '<p>Translated listing content</p>',
            ],
        );
        foreach ([$typeTranslation, $listingTranslation] as $translation) {
            $translation = $repository->transition($translation, TranslationStatus::Ready);
            $repository->transition($translation, TranslationStatus::Published);
        }

        $this->get('/en/bds?property_type=villa')
            ->assertOk()
            ->assertSee('Riverside villa')
            ->assertSee('Villa');
        $this->get('/en/bds/riverside-villa')
            ->assertOk()
            ->assertSee('Translated listing content', false);

        $page = LandingPage::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'BDS701',
            'page_type' => 'home',
            'slug' => 'home-localized-real-estate',
            'status' => 'published',
            'template' => 'landing',
            'is_home' => true,
        ]);
        $listingBlock = LandingPageBlock::query()->create([
            'landing_page_id' => $page->id,
            'theme_key' => 'BDS701',
            'block_type' => 'bds701_latest_listings',
            'is_visible' => true,
            'settings' => ['limit' => 3],
        ]);
        $typeBlock = LandingPageBlock::query()->create([
            'landing_page_id' => $page->id,
            'theme_key' => 'BDS701',
            'block_type' => 'bds701_property_types',
            'is_visible' => true,
            'settings' => ['limit' => 3],
        ]);
        $builder = app(LandingPageBuilder::class);

        $this->assertSame('Riverside villa', $builder->previewDynamicItems($listingBlock, 'en')[0]['title']);
        $this->assertStringContainsString('/en/bds/riverside-villa', $builder->previewDynamicItems($listingBlock, 'en')[0]['url']);
        $this->assertSame('Villa', $builder->previewDynamicItems($typeBlock, 'en')[0]['title']);
    }

    private function prepareWebsite(): void
    {
        foreach (['cms', 'real-estate'] as $moduleKey) {
            ModuleInstallation::query()->updateOrCreate(
                ['key' => $moduleKey],
                [
                    'name' => $moduleKey,
                    'version' => '1.0.0',
                    'status' => 'enabled',
                ],
            );
        }
        Site::query()->where('website_key', 'website-main')->update(['theme_key' => 'BDS701']);
        SiteProfile::query()->withoutGlobalScopes()->where('website_key', 'website-main')->update([
            'website_type' => 'real_estate',
            'active_theme_key' => 'BDS701',
        ]);
        WebsiteLocale::query()->withoutGlobalScopes()->updateOrCreate(
            ['website_key' => 'website-main', 'locale' => 'en'],
            [
                'is_default' => false,
                'is_enabled_for_editing' => true,
                'is_published' => true,
                'fallback_locale' => 'vi',
                'sort_order' => 2,
            ],
        );
    }
}
