<?php

namespace Tests\Feature\Admin;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CMS-001 — the Page editor's compact section list: each section is a
 * summary row, its fields open in the Edit / Add section modal, and the
 * underlying page_sections data (ids, sort_order, content_json shape) is
 * exactly what the inline Repeater used to save.
 */
class PageSectionManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_sections_load_and_survive_an_unrelated_save_byte_for_byte(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $before = $this->storedSections($page);

        $this->editPage($admin, $page)
            ->fillForm(['title' => 'Renamed Page'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Page', $page->fresh()->title);
        $this->assertSame($before, $this->storedSections($page));
    }

    public function test_legacy_section_data_still_loads_into_the_editor(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);

        $sections = array_values($this->editPage($admin, $page)->get('data.sections'));

        $this->assertCount(3, $sections);
        $this->assertSame('hero', $sections[0]['section_type']);
        $this->assertSame('Welcome home', $sections[0]['content_json']['heading']);
        $this->assertSame([11, 12], $sections[2]['content_json']['media_ids']);
    }

    public function test_the_edit_modal_is_prefilled_with_the_sections_current_content(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $instance = $this->editPage($admin, $page);

        $instance
            ->mountFormComponentAction('sections', 'editSection', ['item' => $this->itemKey($instance, 0)])
            ->assertFormSet([
                'section_type' => 'hero',
                'content_json.heading' => 'Welcome home',
            ], $instance->instance()->getMountedActionSchemaName());
    }

    public function test_editing_one_section_changes_only_that_section(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $before = $this->storedSections($page);
        $instance = $this->editPage($admin, $page);

        $instance
            ->callFormComponentAction('sections', 'editSection', data: [
                'section_type' => 'rich_text',
                'title' => 'Intro copy',
                'is_enabled' => false,
                'content_json' => ['body' => '<p>New intro.</p>'],
            ], arguments: ['item' => $this->itemKey($instance, 1)])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $after = $this->storedSections($page);

        $this->assertSame($before[0], $after[0]);
        $this->assertSame($before[2], $after[2]);
        $this->assertSame($before[1]['id'], $after[1]['id']);
        $this->assertSame('Intro copy', $after[1]['title']);
        $this->assertFalse($after[1]['is_enabled']);
        $this->assertStringContainsString('New intro.', $after[1]['content_json']['body']);
    }

    public function test_editing_a_section_keeps_content_keys_the_form_does_not_know_about(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $instance = $this->editPage($admin, $page);

        $instance
            ->callFormComponentAction('sections', 'editSection', data: [
                'section_type' => 'gallery',
                'title' => 'Photos',
                'is_enabled' => true,
                'content_json' => ['heading' => 'Our work'],
            ], arguments: ['item' => $this->itemKey($instance, 2)])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $gallery = $this->storedSections($page)[2];

        $this->assertSame('Our work', $gallery['content_json']['heading']);
        $this->assertSame([11, 12], $gallery['content_json']['media_ids']);
    }

    public function test_changing_a_sections_type_starts_its_content_fresh(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $instance = $this->editPage($admin, $page);

        $instance
            ->callFormComponentAction('sections', 'editSection', data: [
                'section_type' => 'quote',
                'title' => null,
                'is_enabled' => true,
                'content_json' => ['quote' => 'Less is more.', 'attribution' => 'Mies'],
            ], arguments: ['item' => $this->itemKey($instance, 2)])
            ->call('save')
            ->assertHasNoFormErrors();

        $quote = $this->storedSections($page)[2];

        $this->assertSame('quote', $quote['section_type']);
        $this->assertSame('Less is more.', $quote['content_json']['quote']);
        $this->assertArrayNotHasKey('media_ids', $quote['content_json']);
    }

    public function test_a_section_can_be_added_at_the_end(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);

        $this->editPage($admin, $page)
            ->callFormComponentAction('sections', 'add', data: [
                'section_type' => 'cta',
                'title' => 'Closing banner',
                'is_enabled' => true,
                'content_json' => ['heading' => "Let's talk", 'cta_label' => 'Contact', 'cta_url' => '/contact'],
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $sections = $this->storedSections($page);

        $this->assertCount(4, $sections);
        $this->assertSame('cta', $sections[3]['section_type']);
        $this->assertSame(3, $sections[3]['sort_order']);
        $this->assertSame("Let's talk", $sections[3]['content_json']['heading']);
    }

    public function test_every_section_type_can_be_added_and_saved(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'All Types', 'slug' => 'all-types', 'template' => PageTemplate::Standard->value,
        ], $admin);
        $instance = $this->editPage($admin, $page);

        foreach (PageSectionType::cases() as $type) {
            $instance
                ->callFormComponentAction('sections', 'add', data: [
                    'section_type' => $type->value,
                    'title' => $type->getLabel(),
                    'is_enabled' => true,
                ])
                ->assertHasNoFormComponentActionErrors();
        }

        $instance->call('save')->assertHasNoFormErrors();

        $this->assertSame(
            array_map(fn (PageSectionType $type): string => $type->value, PageSectionType::cases()),
            array_column($this->storedSections($page), 'section_type'),
        );
    }

    public function test_a_section_can_be_deleted(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $before = $this->storedSections($page);
        $instance = $this->editPage($admin, $page);

        $instance
            ->callFormComponentAction('sections', 'delete', arguments: ['item' => $this->itemKey($instance, 1)])
            ->call('save')
            ->assertHasNoFormErrors();

        $after = $this->storedSections($page);

        $this->assertCount(2, $after);
        $this->assertSame([$before[0]['id'], $before[2]['id']], array_column($after, 'id'));
        $this->assertSame($before[2]['content_json'], $after[1]['content_json']);
    }

    public function test_reordering_persists_the_new_sort_order_and_keeps_section_ids(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $before = $this->storedSections($page);
        $instance = $this->editPage($admin, $page);
        $keys = array_keys($instance->get('data.sections'));

        $instance
            ->callFormComponentAction('sections', 'reorder', arguments: ['items' => [$keys[2], $keys[0], $keys[1]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $after = $this->storedSections($page);

        $this->assertSame([$before[2]['id'], $before[0]['id'], $before[1]['id']], array_column($after, 'id'));
        $this->assertSame([0, 1, 2], array_column($after, 'sort_order'));
        $this->assertSame($before[2]['content_json'], $after[0]['content_json']);
    }

    public function test_the_section_list_shows_each_sections_label_and_summary(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);

        $this->editPage($admin, $page)
            ->assertSee('01 · Hero')
            ->assertSee('Welcome home')
            ->assertSee('02 · Rich Text')
            ->assertSee('03 · Gallery — Photos')
            ->assertSee('2 images');
    }

    public function test_the_public_page_renders_the_same_after_an_editor_save(): void
    {
        $admin = $this->admin();
        $page = $this->pageWithSections($admin);
        $page->update(['status' => 'published', 'publish_at' => now()->subMinute()]);
        $before = $this->renderedMain($page);

        $this->editPage($admin, $page)->call('save')->assertHasNoFormErrors();

        $this->assertStringContainsString('Welcome home', $before);
        $this->assertSame($before, $this->renderedMain($page));
    }

    /**
     * Only the page's <main> content — the layout around it carries
     * per-request tokens (form time-trap, font preloads) that differ
     * between any two requests.
     */
    private function renderedMain(Page $page): string
    {
        $html = (string) $this->get('/'.$page->slug)->assertOk()->getContent();
        $start = strpos($html, '<main');
        $end = strpos($html, '</main>');

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    private function editPage(User $admin, Page $page): Testable
    {
        return Livewire::actingAs($admin)->test(EditPage::class, ['record' => $page->getRouteKey()]);
    }

    private function itemKey(Testable $instance, int $index): string
    {
        return array_keys($instance->get('data.sections'))[$index];
    }

    private function pageWithSections(User $admin): Page
    {
        return app(CreatePageAction::class)->handle([
            'title' => 'Sections Page',
            'slug' => 'sections-page',
            'template' => PageTemplate::Standard->value,
            'sections' => [
                ['section_type' => 'hero', 'title' => null, 'is_enabled' => true, 'content_json' => [
                    'heading' => 'Welcome home',
                    'stats' => [['value' => '20+', 'label' => 'Years']],
                ]],
                ['section_type' => 'rich_text', 'title' => null, 'is_enabled' => true, 'content_json' => [
                    'body' => '<p>Original copy.</p>',
                ]],
                // Pre-WEB-101 Gallery shape: a flat media_ids list the form has no field for.
                ['section_type' => 'gallery', 'title' => 'Photos', 'is_enabled' => true, 'content_json' => [
                    'media_ids' => [11, 12],
                ]],
            ],
        ], $admin);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storedSections(Page $page): array
    {
        return $page->sections()->orderBy('sort_order')->get()
            ->map(fn ($section): array => [
                'id' => $section->id,
                'section_type' => $section->section_type,
                'title' => $section->title,
                'sort_order' => $section->sort_order,
                'is_enabled' => $section->is_enabled,
                'content_json' => $section->content_json,
            ])
            ->all();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
