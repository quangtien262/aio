<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\LandingPage;
use App\Models\WebsiteLocale;
use App\Support\Localization\LandingPageLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageSourceRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_edit_marks_published_target_outdated_and_unpublishes_its_route(): void
    {
        WebsiteLocale::query()->withoutGlobalScopes()->where([
            'website_key' => 'website-main',
            'locale' => 'en',
        ])->update(['is_published' => true]);
        $page = LandingPage::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'SHOP601',
            'page_type' => 'landing',
            'slug' => 'chien-dich',
            'status' => 'draft',
            'template' => 'landing',
            'is_home' => false,
        ]);
        $localization = app(LandingPageLocalization::class);

        foreach ([
            'vi' => ['slug' => 'chien-dich', 'title' => 'Chiến dịch'],
            'en' => ['slug' => 'campaign', 'title' => 'Campaign'],
        ] as $locale => $payload) {
            $localization->savePageDraft($page, $locale, $payload);
            $localization->transitionPage($page, $locale, TranslationStatus::Ready);
            $localization->transitionPage($page, $locale, TranslationStatus::Published);
        }

        $localization->savePageDraft($page, 'vi', [
            'slug' => 'chien-dich-moi',
            'title' => 'Chiến dịch đã đổi',
        ]);

        $this->assertSame(
            TranslationStatus::Outdated,
            $page->data()->where('locale', 'en')->firstOrFail()->translation_status,
        );
        $this->assertDatabaseHas('localized_routes', [
            'website_key' => 'website-main',
            'locale' => 'en',
            'resource_type' => LandingPageLocalization::ROUTE_RESOURCE_TYPE,
            'resource_id' => (string) $page->id,
            'path' => '/land/campaign',
            'is_published' => false,
        ]);
    }
}
