<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Filament\Pages\ToolsSettings;
use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolRedirect;
use App\Shared\Support\Features\Features;
use App\Tools\ToolFunctionality;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Admin → Tools (content/SEO/publication managed in the database) combined
 * with Git-deployed tool functionality (App\Tools\ToolRegistry): drafts,
 * private preview, publish validation, unpublishing, public pages, hub,
 * sitemap, SEO and slug redirects.
 */
class ToolsModuleTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    // Registry / functionality

    public function test_the_registry_discovers_deployed_functionalities(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertTrue($registry->has('px-to-rem'));
        $this->assertTrue($registry->has('utm-builder'));
        $this->assertFalse($registry->has('does-not-exist'));
        $this->assertSame('PX to REM Converter (v1.0.0)', $registry->options()['px-to-rem']);
        $this->assertSame(['resources/js/tools/px-to-rem.js'], $registry->find('px-to-rem')->assets());
    }

    public function test_a_newly_registered_functionality_is_offered_in_the_admin(): void
    {
        app(ToolRegistry::class)->register(new class extends ToolFunctionality
        {
            public const KEY = 'roi-calculator';

            public const NAME = 'Website ROI Calculator';
        });

        $this->assertArrayHasKey('roi-calculator', app(ToolRegistry::class)->options());
    }

    // Admin

    public function test_non_admins_cannot_reach_the_tools_admin_or_preview(): void
    {
        $member = $this->member();
        $tool = $this->tool();

        foreach (['/admin/tools', '/admin/tools/create', "/admin/tools/{$tool->slug}/edit", '/admin/tool-categories', '/admin/tools-settings'] as $url) {
            $this->actingAs($member)->get($url)->assertForbidden();
        }

        $this->actingAs($member)->get($tool->previewUrl())->assertForbidden();
        auth()->logout();
        $this->get($tool->previewUrl())->assertForbidden();
    }

    public function test_admin_screens_open(): void
    {
        $admin = $this->admin();
        $tool = $this->tool();

        foreach (['/admin/tools', '/admin/tools/create', "/admin/tools/{$tool->slug}/edit", '/admin/tool-categories', '/admin/tools-settings'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_creates_a_tool_as_a_draft_with_faq_seo_and_related_content(): void
    {
        $admin = $this->admin();
        $other = $this->tool(['name' => 'UTM Builder', 'slug' => 'utm-builder', 'functionality' => 'utm-builder']);
        $service = Service::query()->create(['title' => 'Web Development', 'slug' => 'web-development', 'summary' => 'Sites.', 'is_published' => true]);

        Livewire::actingAs($admin)
            ->test(CreateTool::class)
            ->fillForm([
                'name' => 'PX to REM Converter',
                'slug' => 'px-to-rem-converter',
                'tool_category_id' => $this->category()->id,
                'functionality' => 'px-to-rem',
                'short_description' => 'Convert pixels to rem.',
                'heading' => 'PX to REM Converter',
                'introduction' => 'Convert pixel values to rem units.',
                'how_it_works' => '<p>Divide by the root font size.</p>',
                'faqs' => [['question' => 'What is a rem?', 'answer' => 'A unit relative to the root font size.', 'is_visible' => true]],
                'seo.meta_title' => 'PX to REM Converter | Softphoria',
                'seo.meta_description' => 'Free px to rem converter.',
                'related_tool_ids' => [$other->id],
                'service_id' => $service->id,
                'cta_heading' => 'Building a design system?',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tool = Tool::query()->where('slug', 'px-to-rem-converter')->sole();
        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);
        $this->assertSame($admin->id, $tool->created_by);
        $this->assertSame('What is a rem?', $tool->faqs->first()->question);
        $this->assertSame('PX to REM Converter | Softphoria', $tool->seo->meta_title);
        $this->assertSame([$other->id], $tool->relatedTools->pluck('id')->all());
        $this->assertSame($service->id, $tool->service_id);

        // A draft is never public.
        $this->get('/tools/px-to-rem-converter')->assertNotFound();
    }

    public function test_only_deployed_functionalities_and_valid_slugs_are_accepted(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateTool::class)
            ->fillForm(['name' => 'Bad', 'slug' => 'Bad Slug!', 'functionality' => 'rm-rf'])
            ->call('create')
            ->assertHasFormErrors(['slug', 'functionality']);

        $this->assertSame(0, Tool::query()->count());
    }

    public function test_admin_edits_content_without_touching_the_functionality(): void
    {
        $tool = $this->tool();

        Livewire::actingAs($this->admin())
            ->test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->assertSchemaStateSet(['functionality' => 'px-to-rem', 'seo.meta_title' => 'PX to REM Converter | Softphoria'])
            ->fillForm(['seo.meta_title' => 'Free PX to REM Converter', 'faqs' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $tool->refresh();
        $this->assertSame('Free PX to REM Converter', $tool->seo->meta_title);
        $this->assertSame('px-to-rem', $tool->functionality);
        $this->assertSame(0, $tool->faqs()->count());
    }

    public function test_publish_is_blocked_with_a_clear_list_of_what_is_missing(): void
    {
        $tool = Tool::query()->create(['name' => 'Half done', 'slug' => 'half-done']);

        $missing = app(ToolPublisher::class)->publish($tool, $this->admin());

        $this->assertSame(['Category', 'Tool functionality', 'Page heading (H1)', 'SEO title', 'Meta description'], $missing);
        $this->assertSame(ToolStatus::Draft, $tool->fresh()->status);

        Livewire::actingAs($this->admin())
            ->test(ListTools::class)
            ->callTableAction('publish', $tool)
            ->assertNotified('Cannot publish this tool.');

        $this->assertSame(ToolStatus::Draft, $tool->fresh()->status);
    }

    public function test_a_tool_pointing_to_a_missing_functionality_cannot_be_published(): void
    {
        $tool = $this->tool(['functionality' => 'removed-tool']);

        $this->assertContains('A tool functionality available in this deployment', app(ToolPublisher::class)->publish($tool, null));
        $this->assertSame(ToolStatus::Draft, $tool->fresh()->status);
    }

    public function test_publishing_makes_the_tool_public_and_optional_fields_do_not_block_it(): void
    {
        $tool = $this->tool();

        Livewire::actingAs($this->admin())
            ->test(ListTools::class)
            ->callTableAction('publish', $tool)
            ->assertNotified('Tool published');

        $tool->refresh();
        $this->assertSame(ToolStatus::Published, $tool->status);
        $this->assertNotNull($tool->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tool.published', 'entity_id' => $tool->id]);

        $this->get('/tools/px-to-rem-converter')->assertOk()->assertSee('data-px-rem', false);
        $this->get('/tools')->assertOk()->assertSee('PX to REM Converter');
    }

    public function test_publishing_from_the_editor_saves_the_latest_edits_first(): void
    {
        $tool = $this->tool();

        Livewire::actingAs($this->admin())
            ->test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->fillForm(['heading' => 'Convert PX to REM instantly'])
            ->callAction('publish');

        $this->assertSame(ToolStatus::Published, $tool->fresh()->status);
        $this->get('/tools/px-to-rem-converter')->assertSee('Convert PX to REM instantly');
    }

    public function test_unpublishing_hides_the_tool_everywhere_but_keeps_it(): void
    {
        $tool = $this->published();
        $linking = $this->published(['name' => 'UTM Builder', 'slug' => 'utm-builder', 'functionality' => 'utm-builder']);
        $linking->relatedTools()->attach($tool->id);

        Livewire::actingAs($this->admin())
            ->test(ListTools::class)
            ->callTableAction('unpublish', $tool);

        $this->assertSame(ToolStatus::Unpublished, $tool->fresh()->status);
        $this->assertModelExists($tool);

        $this->get('/tools/px-to-rem-converter')->assertNotFound();
        $this->get('/tools')->assertDontSee('/tools/px-to-rem-converter', false);
        $this->get('/tools/utm-builder')->assertOk()->assertDontSee('/tools/px-to-rem-converter', false);
        $this->get('/sitemap.xml')->assertDontSee('/tools/px-to-rem-converter', false);
    }

    public function test_a_published_tool_cannot_be_deleted_until_unpublished(): void
    {
        $tool = $this->published();

        Livewire::actingAs($this->admin())
            ->test(ListTools::class)
            ->assertTableActionHidden('delete', $tool);

        app(ToolPublisher::class)->unpublish($tool, null);

        Livewire::actingAs($this->admin())
            ->test(ListTools::class)
            ->callTableAction('delete', $tool);

        $this->assertModelMissing($tool);
    }

    public function test_duplicating_copies_content_into_a_new_draft(): void
    {
        $tool = $this->published();
        $tool->faqs()->create(['question' => 'Q?', 'answer' => 'A.', 'sort_order' => 0]);

        Livewire::actingAs($this->admin())
            ->test(ListTools::class)
            ->callTableAction('duplicate', $tool, ['name' => 'EM Converter', 'slug' => 'em-converter', 'functionality' => 'px-to-rem'])
            ->assertHasNoTableActionErrors();

        $copy = Tool::query()->where('slug', 'em-converter')->sole();
        $this->assertSame(ToolStatus::Draft, $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertSame('Q?', $copy->faqs->first()->question);
        $this->assertSame($tool->seo->meta_title, $copy->seo->meta_title);
    }

    // Preview

    public function test_admins_preview_the_real_page_before_publishing_noindexed(): void
    {
        $tool = $this->tool();

        $this->actingAs($this->admin())->get($tool->previewUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('Preview (Draft)')
            ->assertSee('data-px-rem', false)
            ->assertSee('data-tool-preview="true"', false);

        $this->get('/sitemap.xml')->assertDontSee('/tools/', false);
    }

    public function test_the_preview_works_while_the_tools_feature_is_off(): void
    {
        app(Features::class)->set('tools', false);
        $tool = $this->tool();

        $this->actingAs($this->admin())->get($tool->previewUrl())->assertOk();
        $this->get('/tools')->assertNotFound();
    }

    // Public pages, SEO

    public function test_a_published_tool_page_has_full_seo(): void
    {
        $tool = $this->published();
        $tool->faqs()->createMany([
            ['question' => 'Visible question?', 'answer' => 'Yes.', 'is_visible' => true, 'sort_order' => 0],
            ['question' => 'Hidden question?', 'answer' => 'No.', 'is_visible' => false, 'sort_order' => 1],
        ]);

        $html = $this->get('/tools/px-to-rem-converter')->assertOk()->getContent();

        $this->assertStringContainsString('<title>PX to REM Converter | Softphoria</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Convert px to rem for any root size.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/tools/px-to-rem-converter').'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.url('/tools/px-to-rem-converter').'">', $html);
        $this->assertStringContainsString('"@type":"WebApplication"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('Visible question?', $html);
        $this->assertStringNotContainsString('Hidden question?', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringNotContainsString('"price"', $html);
    }

    public function test_query_parameters_never_change_the_canonical(): void
    {
        $this->published();

        $this->get('/tools/px-to-rem-converter?px=24&utm_source=x')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/tools/px-to-rem-converter').'">', false);
    }

    public function test_only_this_tools_assets_load_on_its_page(): void
    {
        $this->published();
        $this->published(['name' => 'UTM Builder', 'slug' => 'utm-builder', 'functionality' => 'utm-builder']);

        $px = $this->get('/tools/px-to-rem-converter')->getContent();
        $home = $this->get('/')->getContent();

        $this->assertStringContainsString('data-px-rem', $px);
        $this->assertStringNotContainsString('data-utm', $px);
        $this->assertStringNotContainsString('px-to-rem', $home);
        $this->assertStringNotContainsString('utm-builder', $home);
    }

    public function test_a_published_tool_whose_code_was_removed_is_not_public(): void
    {
        $tool = $this->published();
        $tool->forceFill(['functionality' => 'removed-tool'])->save();

        $this->get('/tools/px-to-rem-converter')->assertNotFound();
        $this->get('/tools')->assertDontSee('/tools/px-to-rem-converter', false);
        $this->get('/sitemap.xml')->assertDontSee('/tools/px-to-rem-converter', false);
    }

    public function test_the_hub_lists_published_tools_featured_and_non_empty_categories(): void
    {
        $this->published(['is_featured' => true]);
        $this->published(['name' => 'UTM Builder', 'slug' => 'utm-builder', 'functionality' => 'utm-builder', 'tool_category_id' => $this->category('Marketing')->id]);
        $this->tool(['name' => 'Secret Draft', 'slug' => 'secret-draft']);
        ToolCategory::query()->create(['name' => 'Image & Media', 'slug' => 'image-media-x']);

        $this->get('/tools')
            ->assertOk()
            ->assertSee('Popular tools')
            ->assertSee('data-tools-category="marketing"', false)
            ->assertDontSee('data-tools-category="image-media-x"', false)
            ->assertDontSee('Secret Draft')
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('<link rel="canonical" href="'.url('/tools').'">', false);
    }

    public function test_related_tools_and_service_show_only_published_ones(): void
    {
        $tool = $this->published();
        $draft = $this->tool(['name' => 'Draft Tool', 'slug' => 'draft-tool']);
        $live = $this->published(['name' => 'UTM Builder', 'slug' => 'utm-builder', 'functionality' => 'utm-builder']);
        $tool->relatedTools()->attach([$draft->id => ['sort_order' => 0], $live->id => ['sort_order' => 1]]);
        $hidden = Service::query()->create(['title' => 'Hidden Service', 'slug' => 'hidden-service', 'summary' => 'x', 'is_published' => false]);
        $tool->update(['service_id' => $hidden->id]);

        $this->get('/tools/px-to-rem-converter')
            ->assertSee('/tools/utm-builder', false)
            ->assertDontSee('/tools/draft-tool', false)
            ->assertDontSee('Hidden Service');
    }

    public function test_the_cta_is_per_tool_with_a_settings_fallback(): void
    {
        $tool = $this->published();

        $this->get('/tools/px-to-rem-converter')->assertSee('Need help with your website?')->assertSee('data-tool-cta="cta"', false);

        $tool->update(['cta_heading' => 'Building a design system?', 'cta_label' => 'Talk to us', 'cta_url' => '/contact']);

        $this->get('/tools/px-to-rem-converter')->assertSee('Building a design system?')->assertSee('Talk to us')->assertDontSee('Need help with your website?');
    }

    public function test_the_sitemap_lists_published_tools_only(): void
    {
        $this->published();
        $this->tool(['name' => 'Draft', 'slug' => 'draft-tool']);
        $noindex = $this->published(['name' => 'UTM Builder', 'slug' => 'utm-builder', 'functionality' => 'utm-builder']);
        $noindex->seo->update(['robots' => 'noindex, follow']);

        $this->get('/sitemap.xml')
            ->assertSee(url('/tools'), false)
            ->assertSee(url('/tools/px-to-rem-converter'), false)
            ->assertDontSee(url('/tools/draft-tool'), false)
            ->assertDontSee(url('/tools/utm-builder'), false);
    }

    public function test_robots_txt_does_not_block_tools(): void
    {
        $this->get('/robots.txt')->assertOk()->assertDontSee('/tools');
    }

    public function test_switching_tools_off_404s_the_public_pages(): void
    {
        $this->published();
        app(Features::class)->set('tools', false);

        $this->get('/tools')->assertNotFound();
        $this->get('/tools/px-to-rem-converter')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/tools', false);
    }

    public function test_renaming_a_published_tool_301s_the_old_url(): void
    {
        $tool = $this->published();

        Livewire::actingAs($this->admin())
            ->test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->fillForm(['slug' => 'px-rem'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/tools/px-to-rem-converter')->assertRedirect(url('/tools/px-rem'))->assertStatus(301);
        $this->get('/tools/px-rem')->assertOk();

        // Renaming back removes the now-pointless redirect loop.
        Livewire::actingAs($this->admin())
            ->test(EditTool::class, ['record' => 'px-rem'])
            ->fillForm(['slug' => 'px-to-rem-converter'])
            ->call('save');

        $this->assertDatabaseMissing('tool_redirects', ['old_slug' => 'px-to-rem-converter']);
        $this->get('/tools/px-rem')->assertRedirect(url('/tools/px-to-rem-converter'));
    }

    public function test_renaming_a_never_published_draft_creates_no_redirect(): void
    {
        $tool = $this->tool();

        Livewire::actingAs($this->admin())
            ->test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->fillForm(['slug' => 'renamed'])
            ->call('save');

        $this->assertSame(0, ToolRedirect::query()->count());
    }

    public function test_tools_settings_save(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ToolsSettings::class)
            ->fillForm(['title' => 'Free web tools', 'cta_url' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasFormErrors(['cta_url']);

        Livewire::actingAs($this->admin())
            ->test(ToolsSettings::class)
            ->fillForm(['title' => 'Free web tools'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->published();
        $this->get('/tools')->assertSee('Free web tools');
    }

    // Helpers

    private function category(string $name = 'Web Development'): ToolCategory
    {
        return ToolCategory::query()->firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name]);
    }

    /**
     * A complete, publishable draft.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function tool(array $attributes = []): Tool
    {
        $tool = Tool::query()->create([
            'name' => 'PX to REM Converter',
            'slug' => 'px-to-rem-converter',
            'tool_category_id' => $this->category()->id,
            'functionality' => 'px-to-rem',
            'short_description' => 'Convert pixels to rem.',
            'heading' => 'PX to REM Converter',
            'introduction' => 'Convert pixel values to rem units.',
            ...$attributes,
        ]);
        $tool->seo()->create(['meta_title' => "{$tool->name} | Softphoria", 'meta_description' => 'Convert px to rem for any root size.']);

        return $tool;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function published(array $attributes = []): Tool
    {
        $tool = $this->tool($attributes);
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        return $tool->refresh();
    }
}
