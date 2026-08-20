<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\CreatePodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\ListPodcastEpisodes;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Episode CRUD and publication status (Database Specification §5's
 * `podcast_episodes`/`podcast_links` tables) — title, slug, description,
 * artwork, publish date, season/episode, embed URL, streaming links and
 * SEO metadata, per docs/Reference UI/Frontend's approved Podcast List/
 * Episode designs.
 */
class PodcastEpisodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_podcast_episodes(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/podcast-episodes');

        $response->assertForbidden();
    }

    public function test_admin_can_create_an_episode_with_links_and_seo(): void
    {
        $podcast = $this->createPodcast();

        Livewire::actingAs($this->admin())
            ->test(CreatePodcastEpisode::class)
            ->fillForm([
                'podcast_id' => $podcast->id,
                'title' => 'The Power of Presence',
                'slug' => 'the-power-of-presence',
                'description' => 'A conversation about showing up fully.',
                'season' => 2,
                'episode_number' => 15,
                'embed_url' => 'https://example.com/embed/episode-15',
                'status' => PodcastEpisodeStatus::Draft->value,
                'links' => [
                    ['provider' => 'spotify', 'url' => 'https://open.spotify.com/episode/xyz'],
                ],
                'seo' => ['meta_title' => 'The Power of Presence'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $episode = PodcastEpisode::query()->where('slug', 'the-power-of-presence')->firstOrFail();

        $this->assertSame($podcast->id, $episode->podcast_id);
        $this->assertSame(2, $episode->season);
        $this->assertSame(15, $episode->episode_number);
        $this->assertCount(1, $episode->links);
        $this->assertSame('https://open.spotify.com/episode/xyz', $episode->links->first()->url);
        $this->assertSame('The Power of Presence', $episode->seo->meta_title);
    }

    public function test_admin_can_view_the_episode_list(): void
    {
        $episode = $this->createEpisode($this->createPodcast());

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertCanSeeTableRecords([$episode]);
    }

    public function test_admin_can_update_an_episode(): void
    {
        $episode = $this->createEpisode($this->createPodcast());

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['title' => 'Finding Stillness', 'status' => PodcastEpisodeStatus::Published->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertSame('Finding Stillness', $episode->title);
        $this->assertTrue($episode->status === PodcastEpisodeStatus::Published);
    }

    public function test_scheduling_an_episode_requires_a_publish_at_time(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePodcastEpisode::class)
            ->fillForm([
                'podcast_id' => $this->createPodcast()->id,
                'title' => 'Scheduled Episode',
                'slug' => 'scheduled-episode',
                'status' => PodcastEpisodeStatus::Scheduled->value,
                'publish_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['publish_at']);
    }

    public function test_publish_due_command_publishes_scheduled_episodes_whose_time_has_passed(): void
    {
        $episode = $this->createEpisode($this->createPodcast(), [
            'status' => PodcastEpisodeStatus::Scheduled,
            'publish_at' => now()->subMinute(),
        ]);

        $this->artisan('podcast:publish-due-episodes')->assertSuccessful();

        $this->assertTrue($episode->refresh()->status === PodcastEpisodeStatus::Published);
    }

    public function test_admin_can_delete_an_episode(): void
    {
        $episode = $this->createEpisode($this->createPodcast());

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->callTableAction('delete', $episode);

        $this->assertSoftDeleted('podcast_episodes', ['id' => $episode->id]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'All The Things Light Podcast',
            'slug' => 'all-the-things-light-podcast',
            'status' => PodcastStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEpisode(Podcast $podcast, array $overrides = []): PodcastEpisode
    {
        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'The Power of Presence',
            'slug' => 'the-power-of-presence',
            'status' => PodcastEpisodeStatus::Draft,
            ...$overrides,
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
