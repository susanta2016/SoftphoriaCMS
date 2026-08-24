<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Role;
use App\Models\Tag;
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

    public function test_admin_can_create_an_episode_with_links_seo_and_topics(): void
    {
        $podcast = $this->createPodcast();
        $category = Category::query()->create(['type' => 'podcast', 'name' => 'Mindfulness', 'slug' => 'mindfulness']);
        $tag = Tag::query()->create(['name' => 'Presence', 'slug' => 'presence']);

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
                'categoryIds' => [$category->id],
                'tagIds' => [$tag->id],
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
        $this->assertTrue($episode->categories->contains($category));
        $this->assertTrue($episode->tags->contains($tag));
    }

    public function test_create_form_has_a_single_streaming_link_field_with_no_add_option(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePodcastEpisode::class)
            ->assertSee('Streaming Link')
            ->assertDontSee('Add streaming link');
    }

    public function test_edit_form_shows_only_the_episodes_first_streaming_link_when_more_than_one_is_stored(): void
    {
        $episode = $this->createEpisode($this->createPodcast());
        $episode->links()->create(['provider' => 'spotify', 'url' => 'https://open.spotify.com/episode/first', 'sort_order' => 0]);
        $episode->links()->create(['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=second', 'sort_order' => 1]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertDontSee('Add streaming link')
            ->assertFormSet(['links' => [
                ['provider' => 'spotify', 'url' => 'https://open.spotify.com/episode/first'],
            ]]);
    }

    public function test_saving_an_episode_with_more_than_one_stored_link_only_updates_the_first_and_preserves_the_rest(): void
    {
        $episode = $this->createEpisode($this->createPodcast());
        $first = $episode->links()->create(['provider' => 'spotify', 'url' => 'https://open.spotify.com/episode/first', 'sort_order' => 0]);
        $second = $episode->links()->create(['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=second', 'sort_order' => 1]);
        $third = $episode->links()->create(['provider' => 'soundcloud', 'url' => 'https://soundcloud.com/episode/third', 'sort_order' => 2]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['links' => [['provider' => 'apple_podcasts', 'url' => 'https://podcasts.apple.com/podcast/updated']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertCount(3, $episode->links);
        // The first link (same row, same id) was edited in place.
        $this->assertSame($first->id, $episode->links[0]->id);
        $this->assertSame('apple_podcasts', $episode->links[0]->provider->value);
        $this->assertSame('https://podcasts.apple.com/podcast/updated', $episode->links[0]->url);
        // The legacy second/third links this form never showed are untouched.
        $this->assertTrue($episode->links->contains('id', $second->id));
        $this->assertTrue($episode->links->contains('id', $third->id));
        $this->assertSame('https://www.youtube.com/watch?v=second', $episode->links->firstWhere('id', $second->id)->url);
        $this->assertSame('https://soundcloud.com/episode/third', $episode->links->firstWhere('id', $third->id)->url);
    }

    public function test_clearing_the_link_field_on_an_episode_with_multiple_stored_links_only_removes_the_first(): void
    {
        $episode = $this->createEpisode($this->createPodcast());
        $first = $episode->links()->create(['provider' => 'spotify', 'url' => 'https://open.spotify.com/episode/first', 'sort_order' => 0]);
        $second = $episode->links()->create(['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=second', 'sort_order' => 1]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['links' => [['provider' => null, 'url' => null]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertCount(1, $episode->links);
        $this->assertFalse($episode->links->contains('id', $first->id));
        $this->assertTrue($episode->links->contains('id', $second->id));
    }

    public function test_saving_an_episode_with_a_single_stored_link_updates_it_in_place(): void
    {
        $episode = $this->createEpisode($this->createPodcast());
        $link = $episode->links()->create(['provider' => 'spotify', 'url' => 'https://open.spotify.com/episode/original', 'sort_order' => 0]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['links' => [['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=updated']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertCount(1, $episode->links);
        $this->assertSame($link->id, $episode->links->first()->id);
        $this->assertSame('youtube', $episode->links->first()->provider->value);
        $this->assertSame('https://www.youtube.com/watch?v=updated', $episode->links->first()->url);
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
