<?php

namespace Tests\Feature\Admin;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Filament\Resources\LightPostFlags\LightPostFlagResource;
use App\Filament\Resources\LightPostFlags\Pages\ViewLightPostFlag;
use App\Models\LightPost;
use App\Models\LightPostFlag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Flagged Journal Entries" moderation queue (App\Filament\Resources\
 * LightPostFlags\LightPostFlagResource) — scoped to LightPost::flagged()
 * only, a sibling to Tests\Feature\Admin\ReviewFlagResourceTest's "Admin
 * Reviews" coverage.
 */
class LightPostFlagResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin_ui.show_community_menu' => true]);
    }

    public function test_only_flagged_entries_appear_in_the_list(): void
    {
        $flagged = $this->entry();
        $unflagged = $this->entry();
        LightPostFlag::query()->create(['light_post_id' => $flagged->id, 'user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->admin())->get('/admin/flagged-journal-entries');

        $response->assertOk();
        $response->assertSee($flagged->content);
        $response->assertDontSee($unflagged->content);
    }

    public function test_dismissing_reports_clears_flags_without_deleting_the_entry(): void
    {
        $entry = $this->entry();
        LightPostFlag::query()->create(['light_post_id' => $entry->id, 'user_id' => User::factory()->create()->id]);

        Livewire::actingAs($this->admin())
            ->test(ViewLightPostFlag::class, ['record' => $entry->getRouteKey()])
            ->assertOk()
            ->callAction(LightPostFlagResource::dismissAction()->getName())
            ->assertNotified();

        $this->assertSame(0, $entry->flags()->count());
        $this->assertNotNull($entry->fresh());
    }

    public function test_deleting_the_entry_removes_it_from_the_feed(): void
    {
        $entry = $this->entry();
        LightPostFlag::query()->create(['light_post_id' => $entry->id, 'user_id' => User::factory()->create()->id]);

        Livewire::actingAs($this->admin())
            ->test(ViewLightPostFlag::class, ['record' => $entry->getRouteKey()])
            ->assertOk()
            ->callAction(LightPostFlagResource::deleteEntryAction()->getName())
            ->assertNotified();

        $this->assertNull($entry->fresh());
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function entry(): LightPost
    {
        $author = User::factory()->create();

        return app(CreateGratitudeJournalEntryAction::class)->handle(
            $author,
            'A flagged journal entry for the admin queue '.uniqid().'.',
            GratitudeJournalVisibility::Public,
        );
    }
}
