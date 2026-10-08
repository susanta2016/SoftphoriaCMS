<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Models\Page;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Shared\Support\Features\Features;
use App\Tools\ToolPublisher;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * /tools/{slug} fallback: a live Tool always wins, then an old tool slug's
 * 301, then a published CMS Page flagged as a tool guide; anything else
 * 404s as before. Guides are never served at the root /{slug}.
 */
class ToolGuidePageRoutingTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_a_published_tool_guide_is_served_under_tools(): void
    {
        $this->guide();

        $this->get('/tools/instagram-reels-safe-zone')
            ->assertOk()
            ->assertSee('Instagram Reels Safe Zone')
            ->assertSee('<link rel="canonical" href="'.url('/tools/instagram-reels-safe-zone').'">', false);
    }

    public function test_a_live_tool_always_takes_priority_over_a_guide_with_the_same_slug(): void
    {
        $this->publishedTool();
        $this->guide(['slug' => 'px-to-rem-converter', 'title' => 'Guide that must not show']);

        $this->get('/tools/px-to-rem-converter')
            ->assertOk()
            ->assertSee('data-tool-slug="px-to-rem-converter"', false)
            ->assertDontSee('Guide that must not show');
    }

    public function test_the_published_safe_zone_checker_is_never_intercepted_by_a_guide_with_its_slug(): void
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $checker = Tool::query()->where('slug', 'social-video-safe-zone-checker')->sole();
        $this->assertSame([], app(ToolPublisher::class)->publish($checker, null));

        // Created directly, as if the admin's slug check had been bypassed.
        $this->guide(['slug' => 'social-video-safe-zone-checker', 'title' => 'Intercepting guide']);

        $this->get('/tools/social-video-safe-zone-checker')
            ->assertOk()
            ->assertSee('data-tool-functionality="social-video-safe-zone"', false)
            ->assertSee('<link rel="canonical" href="'.url('/tools/social-video-safe-zone-checker').'">', false)
            ->assertDontSee('Intercepting guide');
    }

    public function test_a_renamed_tools_old_slug_still_redirects_to_the_tool(): void
    {
        $tool = $this->publishedTool();
        $tool->update(['slug' => 'px-rem']);
        $tool->redirects()->create(['old_slug' => 'px-to-rem-converter']);

        $this->get('/tools/px-to-rem-converter')->assertRedirect('/tools/px-rem')->assertStatus(301);
    }

    public function test_draft_guides_and_ordinary_pages_are_not_served_under_tools(): void
    {
        $this->guide(['status' => PageStatus::Draft]);
        $this->guide(['slug' => 'about-us', 'title' => 'About us', 'is_tool_guide' => false]);

        $this->get('/tools/instagram-reels-safe-zone')->assertNotFound();
        $this->get('/tools/about-us')->assertNotFound();
        $this->get('/tools/does-not-exist')->assertNotFound();
    }

    public function test_a_guide_is_not_served_at_the_root_url(): void
    {
        $this->guide();

        $this->get('/instagram-reels-safe-zone')->assertNotFound();
    }

    public function test_an_old_guide_slug_redirects_to_its_new_tools_url(): void
    {
        $page = $this->guide();
        $page->update(['slug' => 'reels-safe-zone']);
        $page->redirects()->create(['old_path' => 'instagram-reels-safe-zone', 'redirect_type' => '301', 'is_active' => true]);

        $this->get('/tools/instagram-reels-safe-zone')->assertRedirect('/tools/reels-safe-zone')->assertStatus(301);
        $this->get('/instagram-reels-safe-zone')->assertRedirect('/tools/reels-safe-zone');
    }

    public function test_guides_404_and_leave_the_sitemap_while_tools_are_switched_off(): void
    {
        $this->guide();

        $this->get('/sitemap.xml')->assertSee(url('/tools/instagram-reels-safe-zone'), false);

        app(Features::class)->set('tools', false);

        $this->get('/tools/instagram-reels-safe-zone')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('instagram-reels-safe-zone', false);
    }

    public function test_admins_can_still_preview_a_draft_guide(): void
    {
        $page = $this->guide(['status' => PageStatus::Draft]);

        $this->actingAs($this->admin())->get("/admin/pages/{$page->getRouteKey()}/preview")->assertOk();
    }

    public function test_a_guide_slug_already_used_by_a_tool_is_rejected_in_the_admin(): void
    {
        $this->publishedTool();

        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm(['title' => 'PX guide', 'slug' => 'px-to-rem-converter', 'template' => 'standard', 'is_tool_guide' => true])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guide(array $attributes = []): Page
    {
        return Page::query()->create([
            'title' => 'Instagram Reels Safe Zone',
            'slug' => 'instagram-reels-safe-zone',
            'template' => 'standard',
            'is_tool_guide' => true,
            'status' => PageStatus::Published,
            ...$attributes,
        ]);
    }

    private function publishedTool(): Tool
    {
        $tool = Tool::query()->create([
            'name' => 'PX to REM Converter',
            'slug' => 'px-to-rem-converter',
            'tool_category_id' => ToolCategory::query()->firstOrCreate(['slug' => 'web-development'], ['name' => 'Web Development'])->id,
            'functionality' => 'px-to-rem',
            'short_description' => 'Convert pixels to rem.',
            'heading' => 'PX to REM Converter',
            'introduction' => 'Convert pixel values to rem units.',
        ]);
        $tool->seo()->create(['meta_title' => 'PX to REM Converter | Softphoria', 'meta_description' => 'Convert px to rem for any root size.']);
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        return $tool->refresh();
    }
}
