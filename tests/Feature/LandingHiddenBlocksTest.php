<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingHiddenBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_blocks_are_editable_without_becoming_public_sections(): void
    {
        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Hidden block test',
            'website_type' => 'service',
            'active_theme_key' => 'AUTO850',
        ]);
        $builder = app(LandingPageBuilder::class);
        $admin = Admin::factory()->create(['is_system_owner' => true, 'status' => 'active']);

        foreach (['AUTO850', 'NT502', 'NT503', 'NT504', 'TOOL751'] as $theme) {
            $profile->forceFill(['active_theme_key' => $theme])->save();
            $page = $builder->seedHome('website-main', $theme);
            $hidden = $page->blocks()->firstOrFail();
            $hidden->update(['is_visible' => false]);
            $page->load('blocks.data');

            $publicData = $builder->viewData($page, 'vi');
            $editorData = $builder->viewData($page, 'vi', 'vi', true);
            $this->assertNotContains($hidden->id, array_column($publicData['landingBlocks'], 'id'));
            $this->assertSame([], $publicData['landingEditorBlocks']);
            $this->assertNotContains($hidden->id, array_column($editorData['landingBlocks'], 'id'));
            $this->assertContains($hidden->id, array_column($editorData['landingEditorBlocks'], 'id'));

            $response = $this->actingAs($admin, 'admin')
                ->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
                ->assertOk()
                ->assertSee('class="aio-landing-editor-row is-hidden" data-sort-block-id="'.$hidden->id.'"', false)
                ->assertSee('Đang ẩn')
                ->assertDontSee('data-landing-block-id="'.$hidden->id.'"', false);
            preg_match('/const blocks = (.+);/', $response->getContent(), $match);
            $payload = json_decode($match[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
            $this->assertFalse($payload[$hidden->id]['is_visible']);
        }
    }

    public function test_toolbar_remains_available_when_all_blocks_are_hidden(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main', 'site_name' => 'All hidden',
            'website_type' => 'service', 'active_theme_key' => 'AUTO850',
        ]);
        $page = app(LandingPageBuilder::class)->seedHome('website-main', 'AUTO850');
        $page->blocks()->update(['is_visible' => false]);
        $ids = $page->blocks()->pluck('id');

        $response = $this->actingAs(Admin::factory()->create(['is_system_owner' => true, 'status' => 'active']), 'admin')
            ->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()->assertSee('Đang ẩn');
        foreach ($ids as $id) {
            $response->assertSee('data-sort-block-id="'.$id.'"', false)
                ->assertDontSee('data-landing-block-id="'.$id.'"', false);
        }

        $reversed = $ids->reverse()->values();
        $this->putJson(route('admin.api.landing.pages.blocks.reorder', $page), [
            'blocks' => $reversed->map(fn (int $id, int $index): array => [
                'id' => $id, 'sort_order' => ($index + 1) * 10,
            ])->all(),
        ])->assertOk();
        $this->assertSame($reversed->all(), $page->blocks()->pluck('id')->all());
        $this->assertSame(0, $page->blocks()->where('is_visible', true)->count());
    }
}
