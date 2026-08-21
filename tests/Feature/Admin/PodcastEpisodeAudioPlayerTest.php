<?php

namespace Tests\Feature\Admin;

use App\Filament\Support\Media\MediaPreview;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\ListPodcastEpisodes;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-only audio player/preview for Podcast Episodes — the same
 * private-media streaming mechanism as Track's player (see
 * TrackAudioPlayerTest, App\Filament\Support\Media\MediaPreview), applied
 * to PodcastEpisode's new audio_media_id. embed_url remains a separate,
 * external-only streaming reference and is never used as this source.
 */
class PodcastEpisodeAudioPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_episode_list_shows_an_audio_player_for_an_episode_with_audio(): void
    {
        $episode = $this->createEpisodeWithAudio();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertSee(route('media.stream', $episode->audio), false);
    }

    public function test_the_episode_list_shows_a_no_audio_state_for_an_episode_without_audio(): void
    {
        $this->createEpisodeWithoutAudio();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertSee('No audio')
            ->assertDontSee('<audio', false);
    }

    public function test_the_episode_edit_page_shows_the_audio_player_when_audio_is_set(): void
    {
        $episode = $this->createEpisodeWithAudio();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertSee(route('media.stream', $episode->audio), false);
    }

    public function test_the_episode_edit_page_shows_a_no_audio_state_when_audio_is_not_set(): void
    {
        $episode = $this->createEpisodeWithoutAudio();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertSee('No Audio selected.');
    }

    public function test_an_authorized_admin_can_stream_the_previewed_episode_audio(): void
    {
        $episode = $this->createEpisodeWithAudio();

        $response = $this->actingAs($this->admin())->get(route('media.stream', $episode->audio));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_an_unauthenticated_guest_cannot_stream_the_previewed_episode_audio(): void
    {
        $episode = $this->createEpisodeWithAudio();

        $response = $this->get(route('media.stream', $episode->audio));

        $response->assertForbidden();
    }

    public function test_a_non_admin_cannot_stream_the_previewed_episode_audio(): void
    {
        $episode = $this->createEpisodeWithAudio();
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($nonAdmin)->get(route('media.stream', $episode->audio));

        $response->assertForbidden();
    }

    public function test_the_player_url_never_exposes_the_physical_storage_path(): void
    {
        $episode = $this->createEpisodeWithAudio();

        $html = (string) MediaPreview::audioPlayer($episode->audio);

        $this->assertStringContainsString(route('media.stream', $episode->audio), $html);
        $this->assertStringNotContainsString($episode->audio->path, $html);
        $this->assertStringNotContainsString('/storage/', $html);
    }

    private function createEpisodeWithAudio(): PodcastEpisode
    {
        Storage::fake('local');

        $podcast = $this->createPodcast();

        $path = 'media/audio/episode-player-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($path, 'fake-mp3-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'episode-player-test.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->save();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode With Audio',
            'slug' => 'episode-with-audio-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
            'audio_media_id' => $media->id,
        ]);
    }

    private function createEpisodeWithoutAudio(): PodcastEpisode
    {
        $podcast = $this->createPodcast();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode Without Audio',
            'slug' => 'episode-without-audio-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Player Test Podcast '.uniqid(),
            'slug' => 'player-test-podcast-'.uniqid(),
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
