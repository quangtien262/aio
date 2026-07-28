<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\Admin;
use App\Models\LandingPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingBlockReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reordered_blocks_are_reflected_on_the_public_storefront(): void
    {
        app(ThemeDemoContentProviderRegistry::class)
            ->forTheme('EC910')
            ?->generate('ec910-dola-watch');

        $page = LandingPage::query()
            ->where('website_key', 'website-main')
            ->where('theme_key', 'EC910')
            ->where('is_home', true)
            ->firstOrFail();

        $reorderedIds = $page->blocks()
            ->pluck('id')
            ->reverse()
            ->values();

        $admin = Admin::factory()->create([
            'is_system_owner' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.api.landing.pages.blocks.reorder', $page), [
                'blocks' => $reorderedIds
                    ->values()
                    ->map(fn (int $id, int $index): array => [
                        'id' => $id,
                        'sort_order' => ($index + 1) * 10,
                    ])
                    ->all(),
            ])
            ->assertOk();

        $this->assertSame(
            $reorderedIds->all(),
            $page->fresh()->blocks()->pluck('id')->all(),
        );

        $expectedJson = json_encode(
            $reorderedIds->map(fn (int $id): string => (string) $id)->all(),
        );

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-landing-block-order', false)
            ->assertSee("const orderedIds = {$expectedJson};", false);
    }
}
