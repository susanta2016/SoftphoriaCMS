<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\Podcasts\Pages\CreatePodcast;
use App\Modules\Podcast\Filament\Resources\Podcasts\Pages\EditPodcast;
use App\Modules\Podcast\Filament\Resources\Podcasts\Pages\ListPodcasts;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Podcast show (Database Specification §5's `podcasts` table) — the
 * "About the Podcast" content episodes belong to. See PodcastEpisodeTest
 * for episode CRUD.
 */
class PodcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_podcasts(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/podcasts');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_podcast(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePodcast::class)
            ->fillForm([
                'title' => 'All The Things Light Podcast',
                'description' => 'Conversations that illuminate.',
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('podcasts', [
            'title' => 'All The Things Light Podcast',
            'slug' => 'all-the-things-light-podcast',
        ]);
    }

    public function test_admin_can_view_the_podcast_list(): void
    {
        $podcast = $this->createPodcast();

        Livewire::actingAs($this->admin())
            ->test(ListPodcasts::class)
            ->assertCanSeeTableRecords([$podcast]);
    }

    public function test_admin_can_update_a_podcast(): void
    {
        $podcast = $this->createPodcast();

        Livewire::actingAs($this->admin())
            ->test(EditPodcast::class, ['record' => $podcast->getRouteKey()])
            ->fillForm(['title' => 'Renamed Podcast'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Podcast', $podcast->refresh()->title);
    }

    public function test_deleting_a_podcast_with_episodes_is_blocked(): void
    {
        $podcast = $this->createPodcast();
        PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode One',
            'slug' => 'episode-one',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListPodcasts::class)
            ->callTableAction('deletePodcast', $podcast)
            ->assertNotified();

        $this->assertDatabaseHas('podcasts', ['id' => $podcast->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_podcast_with_no_episodes_succeeds(): void
    {
        $podcast = $this->createPodcast();

        Livewire::actingAs($this->admin())
            ->test(ListPodcasts::class)
            ->callTableAction('deletePodcast', $podcast);

        $this->assertSoftDeleted('podcasts', ['id' => $podcast->id]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'All The Things Light Podcast',
            'slug' => 'all-the-things-light-podcast',
            'status' => PodcastStatus::Draft,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
