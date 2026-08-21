<?php

namespace Database\Seeders;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Models\Category;
use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastLinkProvider;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * On-demand sample data for the Podcast module (Podcast/PodcastEpisode, with
 * real cover art and a real uploaded audio file per episode), mirroring
 * MusicSampleDataSeeder's approach. Not wired into DatabaseSeeder's default
 * run() — opt-in, `php artisan db:seed --class=PodcastSampleDataSeeder`.
 * Idempotent on each show's slug; leaves any existing Podcast (e.g. one
 * created by hand while testing the admin panel) untouched.
 *
 * "Video" note: PodcastEpisode has no uploaded video Media field (only the
 * new audio_media_id, private, and embed_url, always external — see
 * app/Modules/Podcast/database/migrations/2026_08_25_090000_add_audio_media_id_to_podcast_episodes_table.php's
 * docblock). Same resolution as MusicSampleDataSeeder's album/track
 * embed_video_url: a real audio file is uploaded through
 * StoreUploadedMediaAction (private `local` disk, admin-preview-playable),
 * and "video" is represented the only way this schema currently supports —
 * an external embed_url pointing at a video source. If/when Podcast gets a
 * real uploaded video field, this is the seeder to extend.
 */
class PodcastSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $uploader = User::query()->firstOrFail();

        $categories = collect(['Mindfulness', 'Conversations', 'Wellbeing', 'Relationships'])
            ->mapWithKeys(fn (string $name) => [
                $name => Category::query()->firstOrCreate(
                    ['type' => 'podcast', 'slug' => str($name)->slug()->value()],
                    ['name' => $name],
                ),
            ]);

        $tags = Tag::query()->whereIn('name', ['Presence', 'Inner Peace', 'Self-Awareness', 'Relationships'])
            ->get()->keyBy('name');

        $this->seedPodcast(
            title: 'Conversations on Presence',
            slug: 'conversations-on-presence',
            description: 'Long-form conversations about slowing down, listening deeply, and staying present with what is.',
            coverColor: [150, 122, 168],
            uploader: $uploader,
            episodes: [
                [
                    'title' => 'The Art of Listening',
                    'season' => 1, 'episodeNumber' => 1,
                    'description' => 'What changes when we actually listen — to others, and to ourselves.',
                    'embedUrl' => 'https://www.youtube.com/watch?v=cop-ep001',
                    'categories' => ['Mindfulness', 'Conversations'],
                    'tags' => ['Presence', 'Inner Peace'],
                    'links' => [
                        PodcastLinkProvider::Spotify->value => 'https://open.spotify.com/episode/cop-ep001',
                        PodcastLinkProvider::ApplePodcasts->value => 'https://podcasts.apple.com/podcast/cop-ep001',
                        PodcastLinkProvider::YouTube->value => 'https://www.youtube.com/watch?v=cop-ep001',
                    ],
                ],
                [
                    'title' => 'Finding Stillness in Motion',
                    'season' => 1, 'episodeNumber' => 2,
                    'description' => 'Stillness isn\'t the absence of movement — a conversation on carrying calm through a full day.',
                    'embedUrl' => 'https://www.youtube.com/watch?v=cop-ep002',
                    'categories' => ['Mindfulness', 'Wellbeing'],
                    'tags' => ['Inner Peace'],
                    'links' => [
                        PodcastLinkProvider::Spotify->value => 'https://open.spotify.com/episode/cop-ep002',
                        PodcastLinkProvider::SoundCloud->value => 'https://soundcloud.com/conversations-on-presence/ep002',
                    ],
                ],
                [
                    'title' => 'A Conversation on Grief',
                    'season' => 1, 'episodeNumber' => 3,
                    'description' => 'An honest, unhurried conversation about grief, and what it means to sit with it rather than rush through it.',
                    'embedUrl' => 'https://www.youtube.com/watch?v=cop-ep003',
                    'categories' => ['Conversations', 'Wellbeing'],
                    'tags' => ['Self-Awareness'],
                    'links' => [
                        PodcastLinkProvider::Spotify->value => 'https://open.spotify.com/episode/cop-ep003',
                        PodcastLinkProvider::ApplePodcasts->value => 'https://podcasts.apple.com/podcast/cop-ep003',
                    ],
                ],
            ],
        );

        $this->seedPodcast(
            title: 'The Inner Work',
            slug: 'the-inner-work',
            description: 'Short, practical episodes on the quiet work of becoming — boundaries, rest, and self-trust.',
            coverColor: [122, 150, 138],
            uploader: $uploader,
            episodes: [
                [
                    'title' => 'Why We Resist Rest',
                    'season' => null, 'episodeNumber' => 1,
                    'description' => 'Rest isn\'t a reward you earn — unpacking why it so often feels like one.',
                    'embedUrl' => 'https://www.youtube.com/watch?v=tiw-ep001',
                    'categories' => ['Wellbeing'],
                    'tags' => ['Self-Awareness'],
                    'links' => [
                        PodcastLinkProvider::Spotify->value => 'https://open.spotify.com/episode/tiw-ep001',
                    ],
                ],
                [
                    'title' => 'Boundaries as Love',
                    'season' => null, 'episodeNumber' => 2,
                    'description' => 'Reframing boundaries as an act of care — for yourself, and for the relationship.',
                    'embedUrl' => 'https://www.youtube.com/watch?v=tiw-ep002',
                    'categories' => ['Relationships', 'Wellbeing'],
                    'tags' => ['Relationships', 'Self-Awareness'],
                    'links' => [
                        PodcastLinkProvider::Spotify->value => 'https://open.spotify.com/episode/tiw-ep002',
                        PodcastLinkProvider::AmazonMusic->value => 'https://music.amazon.com/podcasts/the-inner-work/tiw-ep002',
                    ],
                ],
            ],
        );

        $this->command?->info('Podcast sample data seeded: 2 podcasts, 5 episodes — each with real cover art and a real uploaded audio file.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $episodes
     */
    private function seedPodcast(string $title, string $slug, string $description, array $coverColor, User $uploader, array $episodes): void
    {
        if (Podcast::query()->where('slug', $slug)->exists()) {
            $this->command?->warn("Podcast \"{$title}\" already exists — skipping.");

            return;
        }

        $cover = $this->makeCoverImage($title, $coverColor, $uploader);

        $podcast = Podcast::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'artwork_media_id' => $cover->id,
            'status' => PodcastStatus::Published,
        ]);

        foreach ($episodes as $episodeData) {
            $this->seedEpisode($podcast, $episodeData, $uploader);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedEpisode(Podcast $podcast, array $data, User $uploader): void
    {
        $audio = $this->makeAudioMedia($data['title'], $uploader);
        $episodeCover = $this->makeCoverImage($data['title'], [168, 140, 122], $uploader);

        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => $data['title'],
            'slug' => str($data['title'])->slug()->value(),
            'description' => $data['description'] ?? null,
            'artwork_media_id' => $episodeCover->id,
            'publish_date' => now()->subWeeks(random_int(1, 20))->startOfWeek(),
            'season' => $data['season'] ?? null,
            'episode_number' => $data['episodeNumber'] ?? null,
            'embed_url' => $data['embedUrl'] ?? null,
            'audio_media_id' => $audio->id,
            'status' => PodcastEpisodeStatus::Published,
        ]);

        foreach ($data['links'] ?? [] as $provider => $url) {
            $episode->links()->create(['provider' => $provider, 'url' => $url, 'sort_order' => 0]);
        }

        if (! empty($data['categories'])) {
            $categoryIds = Category::query()->where('type', 'podcast')
                ->whereIn('name', $data['categories'])->pluck('id')->all();
            $episode->categories()->sync($categoryIds);
        }

        if (! empty($data['tags'])) {
            $tagIds = Tag::query()->whereIn('name', $data['tags'])->pluck('id')->all();
            $episode->tags()->sync($tagIds);
        }
    }

    /**
     * A real, valid MPEG audio stream — same approach as
     * MusicSampleDataSeeder — written to the `audio` category's real disk
     * (`local`, private) via StoreUploadedMediaAction.
     */
    private function makeAudioMedia(string $title, User $uploader): Media
    {
        $category = MediaCategory::Audio;
        $frame = chr(0xFF).chr(0xFB).chr(0x90).chr(0x44).str_repeat(chr(0x00), 417 - 4);
        $bytes = str_repeat($frame, 30);

        $path = trim($category->directory(), '/').'/'.str($title)->slug()->value().'-'.substr(md5($title.microtime()), 0, 8).'.mp3';
        Storage::disk($category->diskName())->put($path, $bytes);

        return app(StoreUploadedMediaAction::class)->handle(
            disk: $category->diskName(),
            path: $path,
            uploader: $uploader,
            visibility: $category->visibility(),
        );
    }

    /**
     * A real, valid PNG (solid color, GD-rendered) written to the `image`
     * category's real public disk — same approach as MusicSampleDataSeeder.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function makeCoverImage(string $title, array $rgb, User $uploader): Media
    {
        $category = MediaCategory::Image;
        $size = 800;

        $image = imagecreatetruecolor($size, $size);
        $color = imagecolorallocate($image, ...$rgb);
        imagefilledrectangle($image, 0, 0, $size, $size, $color);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $path = trim($category->directory(), '/').'/'.str($title)->slug()->value().'-'.substr(md5($title.microtime()), 0, 8).'.png';
        Storage::disk($category->diskName())->put($path, $bytes);

        return app(StoreUploadedMediaAction::class)->handle(
            disk: $category->diskName(),
            path: $path,
            uploader: $uploader,
            visibility: $category->visibility(),
        );
    }
}
