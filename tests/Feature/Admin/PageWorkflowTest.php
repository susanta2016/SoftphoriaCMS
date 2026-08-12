<?php

namespace Tests\Feature\Admin;

use App\Actions\Page\ArchivePageAction;
use App\Actions\Page\CreatePageAction;
use App\Actions\Page\DeletePageAction;
use App\Actions\Page\PublishPageAction;
use App\Actions\Page\RestorePageRevisionAction;
use App\Actions\Page\SchedulePageAction;
use App\Actions\Page\UnpublishPageAction;
use App\Actions\Page\UpdatePageAction;
use App\Console\Commands\PublishDuePagesCommand;
use App\Enums\MenuItemDestinationType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Exceptions\Page\PageInUseByNavigationException;
use App\Models\Menu;
use App\Models\PageRedirect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADMIN-006 Pages — the Action-layer workflow: status transitions,
 * revisions (snapshot + restore), slug-change redirects, SEO metadata,
 * structured sections, and the scheduled auto-publish command.
 */
class PageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_action_writes_an_initial_revision(): void
    {
        $admin = $this->admin();

        $page = app(CreatePageAction::class)->handle([
            'title' => 'About',
            'slug' => 'about',
            'template' => PageTemplate::About->value,
            'sections' => [
                ['section_type' => 'rich_text', 'content_json' => ['body' => 'Hello'], 'is_enabled' => true],
            ],
        ], $admin);

        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame(1, $page->sections()->count());
        $this->assertSame('rich_text', $page->sections()->first()->section_type);
    }

    public function test_update_page_action_creates_a_redirect_when_the_slug_changes(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'About', 'slug' => 'about-old', 'template' => PageTemplate::About->value,
        ], $admin);

        app(UpdatePageAction::class)->handle($page, ['title' => 'About', 'slug' => 'about-new'], $admin);

        $this->assertSame('about-new', $page->fresh()->slug);
        $this->assertDatabaseHas((new PageRedirect)->getTable(), [
            'old_path' => 'about-old',
            'page_id' => $page->id,
            'redirect_type' => '301',
        ]);
    }

    public function test_update_page_action_writes_a_new_revision_and_syncs_sections(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value,
            'sections' => [['section_type' => 'hero', 'content_json' => ['heading' => 'Hi'], 'is_enabled' => true]],
        ], $admin);

        $sectionId = $page->sections()->first()->id;

        app(UpdatePageAction::class)->handle($page, [
            'title' => 'About',
            'slug' => 'about',
            'sections' => [
                ['id' => $sectionId, 'section_type' => 'hero', 'content_json' => ['heading' => 'Updated'], 'is_enabled' => true],
                ['section_type' => 'quote', 'content_json' => ['quote' => 'New'], 'is_enabled' => true],
            ],
        ], $admin);

        $page->refresh();
        $this->assertSame(2, $page->sections()->count());
        $this->assertSame('Updated', $page->sections()->find($sectionId)->content_json['heading']);
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_update_page_action_saves_seo_metadata(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value,
        ], $admin);

        app(UpdatePageAction::class)->handle($page, [
            'title' => 'About',
            'slug' => 'about',
            'seo' => ['meta_title' => 'About Us', 'meta_description' => 'Learn more.'],
        ], $admin);

        $page->refresh();
        $this->assertSame('About Us', $page->seo->meta_title);
        $this->assertSame('Learn more.', $page->seo->meta_description);
    }

    public function test_publish_unpublish_archive_and_schedule_transitions(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value,
        ], $admin);

        app(PublishPageAction::class)->handle($page, $admin);
        $this->assertSame(PageStatus::Published, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->publish_at);

        app(UnpublishPageAction::class)->handle($page, $admin);
        $this->assertSame(PageStatus::Draft, $page->fresh()->status);

        app(ArchivePageAction::class)->handle($page, $admin);
        $this->assertSame(PageStatus::Archived, $page->fresh()->status);

        $future = Carbon::now()->addDay();
        app(SchedulePageAction::class)->handle($page, $future, $admin);
        $this->assertSame(PageStatus::Scheduled, $page->fresh()->status);
        $this->assertSame($future->toDateTimeString(), $page->fresh()->publish_at->toDateTimeString());
    }

    public function test_restore_revision_action_restores_attributes_and_sections_and_adds_a_new_revision(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Original Title', 'slug' => 'about', 'template' => PageTemplate::About->value,
            'sections' => [['section_type' => 'quote', 'content_json' => ['quote' => 'v1'], 'is_enabled' => true]],
        ], $admin);

        $v1 = $page->revisions()->first();

        app(UpdatePageAction::class)->handle($page, [
            'title' => 'Changed Title',
            'slug' => 'about',
            'sections' => [['section_type' => 'quote', 'content_json' => ['quote' => 'v2'], 'is_enabled' => true]],
        ], $admin);

        $this->assertSame('Changed Title', $page->fresh()->title);

        app(RestorePageRevisionAction::class)->handle($page, $v1, $admin);

        $page->refresh();
        $this->assertSame('Original Title', $page->title);
        $this->assertSame('v1', $page->sections()->first()->content_json['quote']);
        $this->assertSame(3, $page->revisions()->count());
    }

    public function test_publish_due_pages_command_publishes_only_scheduled_pages_whose_time_has_passed(): void
    {
        $admin = $this->admin();

        $due = app(CreatePageAction::class)->handle(['title' => 'Due', 'slug' => 'due', 'template' => PageTemplate::Standard->value], $admin);
        $due->status = PageStatus::Scheduled;
        $due->publish_at = now()->subMinute();
        $due->save();

        $notYetDue = app(CreatePageAction::class)->handle(['title' => 'Not Due', 'slug' => 'not-due', 'template' => PageTemplate::Standard->value], $admin);
        $notYetDue->status = PageStatus::Scheduled;
        $notYetDue->publish_at = now()->addDay();
        $notYetDue->save();

        $this->artisan(PublishDuePagesCommand::class)->assertSuccessful();

        $this->assertSame(PageStatus::Published, $due->fresh()->status);
        $this->assertSame(PageStatus::Scheduled, $notYetDue->fresh()->status);
    }

    public function test_delete_page_action_soft_deletes(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle(['title' => 'Temp', 'slug' => 'temp', 'template' => PageTemplate::Standard->value], $admin);

        app(DeletePageAction::class)->handle($page, $admin);

        $this->assertSoftDeleted($page);
    }

    public function test_delete_page_action_blocks_deletion_when_referenced_by_an_enabled_navigation_item(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle(['title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value], $admin);
        app(PublishPageAction::class)->handle($page, $admin);

        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $menu->items()->create([
            'label' => 'About',
            'destination_type' => MenuItemDestinationType::Page->value,
            'page_id' => $page->id,
            'is_enabled' => true,
        ]);

        $this->expectException(PageInUseByNavigationException::class);

        app(DeletePageAction::class)->handle($page, $admin);
    }

    /**
     * A disabled item still holds a stale page_id once the page is gone —
     * blocking must not be conditional on is_enabled, or a soft-deleted
     * page could sit behind a disabled nav item indefinitely.
     */
    public function test_delete_page_action_blocks_deletion_when_referenced_by_a_disabled_navigation_item(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle(['title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value], $admin);
        app(PublishPageAction::class)->handle($page, $admin);

        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $menu->items()->create([
            'label' => 'About',
            'destination_type' => MenuItemDestinationType::Page->value,
            'page_id' => $page->id,
            'is_enabled' => false,
        ]);

        $this->expectException(PageInUseByNavigationException::class);

        app(DeletePageAction::class)->handle($page, $admin);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
