<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\CmsPage;
use App\Support\Localization\CmsPageLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageSourceRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_source_page_marks_published_target_outdated_and_unpublishes_route(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giới thiệu',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => '<p>Nội dung nguồn</p>',
        ]);
        $localization = app(CmsPageLocalization::class);
        $target = $localization->saveDraft($page, 'en', [
            'title' => 'About us',
            'slug' => 'about-us',
            'body' => '<p>English content</p>',
        ]);
        $localization->transition($page, 'en', TranslationStatus::Ready);
        $localization->transition($page, 'en', TranslationStatus::Published);

        $localization->saveDraft($page, 'vi', [
            'title' => 'Giới thiệu mới',
            'slug' => 'gioi-thieu',
            'body' => '<p>Nội dung nguồn đã đổi</p>',
        ]);

        $this->assertSame(TranslationStatus::Outdated, $target->fresh()->translation_status);
        $this->assertDatabaseHas('localized_routes', [
            'resource_type' => 'cms_page',
            'resource_id' => (string) $page->id,
            'locale' => 'en',
            'is_published' => false,
        ]);
    }
}
