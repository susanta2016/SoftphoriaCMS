<?php

namespace Database\Seeders;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Models\Category;
use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use App\Modules\Music\Enums\MusicLinkProvider;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * On-demand sample data for the Music module (Album/Single/Track, with real
 * cover art and real audio files) so the admin panel and Purchase Readiness
 * panel have something realistic to browse against. Not wired into
 * DatabaseSeeder's default run() — this is deliberately opt-in, run by hand
 * with `php artisan db:seed --class=MusicSampleDataSeeder`, so it never
 * fires on a fresh install/CI run. Idempotent: firstOrCreate on each
 * release's slug, safe to re-run.
 *
 * Cover art and audio are real, valid files (a generated PNG, a valid MPEG
 * frame stream) written through the exact same StoreUploadedMediaAction /
 * MediaCategory disk rules the Filament MediaPicker itself uses — audio
 * lands on the private `local` disk, covers on the public disk — so every
 * seeded Track is genuinely "ready for purchase" per
 * CheckAlbumReadinessAction/CheckSingleReadinessAction.
 */
class MusicSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $uploader = User::query()->firstOrFail();

        $categories = collect(['Acoustic', 'Devotional', 'Meditation', 'Uplifting'])
            ->mapWithKeys(fn (string $name) => [
                $name => Category::query()->firstOrCreate(
                    ['type' => 'music', 'slug' => str($name)->slug()->value()],
                    ['name' => $name],
                ),
            ]);

        $tags = Tag::query()->whereIn('name', ['Presence', 'Inner Peace', 'Self-Awareness', 'Mindfulness'])
            ->get()->keyBy('name');

        $this->seedAlbum(
            title: 'Quiet Mornings',
            slug: 'quiet-mornings',
            description: 'A collection of songs written in the stillness before sunrise — an invitation into presence and gentle beginnings.',
            coverColor: [214, 178, 122],
            isFeatured: true,
            embedVideoUrl: 'https://www.youtube.com/watch?v=qm-sample-001',
            // provider is legacy metadata only (see MusicLinkProvider's own
            // docblock) — these keys just avoid duplicate array keys below;
            // the url is what the custom <audio> player actually plays, so
            // it must be a direct, browser-playable audio file, never a
            // provider webpage URL. SoundHelix's example MP3s are a
            // long-standing, freely-licensed, auth-free source built
            // specifically for this kind of player demo/testing.
            links: [
                MusicLinkProvider::Spotify->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                MusicLinkProvider::AppleMusic->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                MusicLinkProvider::YouTube->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
            ],
            uploader: $uploader,
            tracks: [
                [
                    'title' => 'Here I Am',
                    'description' => 'An invitation to presence, written during a season of deep listening.',
                    'writtenBy' => 'IAWARII', 'producedBy' => 'IAWARII', 'isrc' => 'US-AT2-26-00001',
                    'duration' => 218,
                    'lyrics' => "Here I am, in the quiet of the morning\nNo more running, no more warning\nJust this breath, just this light\nHere I am, making peace with the night",
                    'songStory' => 'This song was born during a season of deep listening — sitting with what is, rather than reaching for what could be.',
                    'credits' => [['role' => 'Vocals, Lyrics, Composition', 'name' => 'IAWARII'], ['role' => 'Piano', 'name' => 'Dominique Crist']],
                    'categories' => ['Devotional', 'Meditation'],
                    'tags' => ['Presence', 'Inner Peace'],
                ],
                [
                    'title' => 'Still Water',
                    'description' => 'A meditation on stillness as strength, not absence.',
                    'writtenBy' => 'IAWARII', 'producedBy' => 'IAWARII', 'isrc' => 'US-AT2-26-00002',
                    'duration' => 194,
                ],
                [
                    'title' => 'Morning Light',
                    'description' => 'The closing song of the collection — a return to gratitude.',
                    'writtenBy' => 'IAWARII', 'producedBy' => 'Dominique Crist', 'isrc' => 'US-AT2-26-00003',
                    'duration' => 241,
                ],
            ],
        );

        $this->seedAlbum(
            title: 'Return to Center',
            slug: 'return-to-center',
            description: 'Three songs about coming home to yourself, recorded live in a single afternoon.',
            coverColor: [122, 150, 168],
            isFeatured: false,
            embedVideoUrl: null,
            links: [
                MusicLinkProvider::SoundCloud->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
                MusicLinkProvider::Spotify->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3',
            ],
            uploader: $uploader,
            tracks: [
                ['title' => 'Breath', 'description' => 'One long exhale, set to music.', 'duration' => 176],
                ['title' => 'Anchor', 'description' => 'For the days that feel unmoored.', 'duration' => 203],
                ['title' => 'Coming Home', 'description' => 'The title track — recorded in one take.', 'duration' => 229],
            ],
        );

        $this->seedSingle(
            title: 'Presence',
            slug: 'presence',
            description: 'A single written after a week of silence — presence as the whole practice.',
            coverColor: [178, 122, 142],
            isFeatured: true,
            embedVideoUrl: 'https://www.youtube.com/watch?v=presence-sample-001',
            links: [
                MusicLinkProvider::Spotify->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-6.mp3',
                MusicLinkProvider::AppleMusic->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-7.mp3',
                MusicLinkProvider::YouTube->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3',
            ],
            uploader: $uploader,
            track: [
                'title' => 'Presence',
                'description' => 'The whole practice, in one word — and one song.',
                'writtenBy' => 'IAWARII', 'producedBy' => 'IAWARII', 'isrc' => 'US-AT2-26-00010',
                'duration' => 187,
                'lyrics' => "Presence is the whole practice\nNothing to fix, nothing to chase\nJust this moment, just this face\nPresence is the whole practice",
                'songStory' => 'Written after a week of silence — the realization that presence was never something to achieve.',
                'credits' => [['role' => 'Vocals, Lyrics, Composition', 'name' => 'IAWARII']],
                'categories' => ['Meditation', 'Uplifting'],
                'tags' => ['Presence', 'Mindfulness'],
            ],
        );

        $this->seedSingle(
            title: 'Gratitude',
            slug: 'gratitude',
            description: 'A short, simple song of thanks.',
            coverColor: [168, 158, 96],
            isFeatured: false,
            embedVideoUrl: null,
            links: [
                MusicLinkProvider::Spotify->value => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-9.mp3',
            ],
            uploader: $uploader,
            track: [
                'title' => 'Gratitude',
                'description' => 'A short, simple song of thanks.',
                'writtenBy' => 'IAWARII', 'producedBy' => 'IAWARII', 'isrc' => 'US-AT2-26-00011',
                'duration' => 152,
            ],
        );

        $this->command?->info('Music sample data seeded: 2 albums, 2 singles, 8 tracks — all with real cover art and audio.');
    }

    /**
     * @param  array<string, string>  $links
     * @param  array<int, array<string, mixed>>  $tracks
     */
    private function seedAlbum(
        string $title,
        string $slug,
        string $description,
        array $coverColor,
        bool $isFeatured,
        ?string $embedVideoUrl,
        array $links,
        User $uploader,
        array $tracks,
    ): void {
        $existing = Album::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            $this->command?->warn("Album \"{$title}\" already exists — skipping.");

            return;
        }

        $cover = $this->makeCoverImage($title, $coverColor, $uploader);

        $album = Album::query()->create([
            'title' => $title,
            'slug' => $slug,
            'release_date' => now()->subMonths(random_int(1, 18))->startOfMonth(),
            'description' => $description,
            'cover_media_id' => $cover->id,
            'embed_video_url' => $embedVideoUrl,
            'status' => ReleaseStatus::Published,
            'is_featured' => $isFeatured,
        ]);

        foreach ($links as $provider => $url) {
            $album->streamingLinks()->create(['provider' => $provider, 'url' => $url, 'sort_order' => 0]);
        }

        foreach (array_values($tracks) as $index => $trackData) {
            $this->seedTrack($album, null, $index + 1, $trackData, $uploader);
        }
    }

    /**
     * @param  array<string, string>  $links
     * @param  array<string, mixed>  $track
     */
    private function seedSingle(
        string $title,
        string $slug,
        string $description,
        array $coverColor,
        bool $isFeatured,
        ?string $embedVideoUrl,
        array $links,
        User $uploader,
        array $track,
    ): void {
        $existing = Single::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            $this->command?->warn("Single \"{$title}\" already exists — skipping.");

            return;
        }

        $cover = $this->makeCoverImage($title, $coverColor, $uploader);

        $single = Single::query()->create([
            'title' => $title,
            'slug' => $slug,
            'release_date' => now()->subMonths(random_int(1, 12))->startOfMonth(),
            'description' => $description,
            'cover_media_id' => $cover->id,
            'status' => ReleaseStatus::Published,
            'is_featured' => $isFeatured,
        ]);

        foreach ($links as $provider => $url) {
            $single->streamingLinks()->create(['provider' => $provider, 'url' => $url, 'sort_order' => 0]);
        }

        $track['embedVideoUrl'] = $embedVideoUrl;
        $this->seedTrack(null, $single, null, $track, $uploader);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedTrack(?Album $album, ?Single $single, ?int $trackNumber, array $data, User $uploader): void
    {
        $audio = $this->makeAudioMedia($data['title'], $data['duration'] ?? 180, $uploader);

        $track = Track::query()->create([
            'album_id' => $album?->id,
            'single_id' => $single?->id,
            'title' => $data['title'],
            'slug' => str($data['title'])->slug()->value(),
            'track_number' => $trackNumber,
            'duration_seconds' => $data['duration'] ?? null,
            'description' => $data['description'] ?? null,
            'written_by' => $data['writtenBy'] ?? null,
            'produced_by' => $data['producedBy'] ?? null,
            'isrc' => $data['isrc'] ?? null,
            'video_embed_url' => $data['embedVideoUrl'] ?? null,
            'audio_media_id' => $audio->id,
            'status' => TrackStatus::Published,
        ]);

        if (isset($data['lyrics'])) {
            $track->lyrics()->create(['content' => $data['lyrics'], 'visibility' => 'public']);
        }

        if (isset($data['songStory'])) {
            $track->songStory()->create(['content' => $data['songStory']]);
        }

        foreach ($data['credits'] ?? [] as $sortOrder => $credit) {
            $track->credits()->create([...$credit, 'sort_order' => $sortOrder]);
        }

        if (! empty($data['categories'])) {
            $categoryIds = Category::query()->where('type', 'music')
                ->whereIn('name', $data['categories'])->pluck('id')->all();
            $track->categories()->sync($categoryIds);
        }

        if (! empty($data['tags'])) {
            $tagIds = Tag::query()->whereIn('name', $data['tags'])->pluck('id')->all();
            $track->tags()->sync($tagIds);
        }
    }

    /**
     * A real, valid MPEG audio stream — long enough to be a plausible song
     * length — written straight to the `audio` category's real disk
     * (`local`, private) via the same StoreUploadedMediaAction the
     * MediaPicker upload action calls.
     */
    private function makeAudioMedia(string $title, int $durationSeconds, User $uploader): Media
    {
        $category = MediaCategory::Audio;
        $frame = chr(0xFF).chr(0xFB).chr(0x90).chr(0x44).str_repeat(chr(0x00), 417 - 4);
        // ~1 frame per 26ms at this bitrate/samplerate — capped so seed data
        // stays fast and small regardless of the "duration" a track claims.
        $frameCount = min(200, (int) ceil($durationSeconds * 1000 / 26));
        $bytes = str_repeat($frame, max(10, $frameCount));

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
     * category's real public disk, same as an admin's own cover-art upload.
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
