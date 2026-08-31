<?php

namespace Database\Seeders;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\PageSectionType;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the client's three required sections onto the *existing* "about"
 * CMS Page (About All the Things Light / About Cory Gold / About Jacob
 * d'IAWARII) through the same UpdatePageAction the Pages admin UI uses —
 * see HomePageSeeder for the identical idempotent pattern. Unlike
 * HomePageSeeder/PrivacyPolicyPageSeeder this never creates a Page: the
 * About page is required to already exist, so a missing "about" slug is a
 * no-op rather than standing up a second one.
 *
 * Cory Gold's and Jacob's written content is intentionally left empty —
 * the client has not supplied that copy yet; nothing here should be read
 * as placeholder biography text.
 *
 * The Jacob section's video was supplied as an already-placed file at the
 * Video category's own disk/directory (storage/app/private/media/video/)
 * rather than through the admin uploader. This seeder registers it into
 * the Media Library the first time it runs, via the exact same
 * StoreUploadedMediaAction every other upload path uses — the file is
 * read in place, never copied/moved — then just looks it up by path on
 * every later run.
 */
class AboutPageSeeder extends Seeder
{
    private const string JACOB_VIDEO_DISK = 'local';

    private const string JACOB_VIDEO_PATH = "media/video/About Jacob d'IAWARII.mp4";

    public function run(): void
    {
        $page = Page::query()->where('slug', 'about')->first();

        if (! $page) {
            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $jacobVideoMediaId = $this->resolveJacobVideoMediaId($actor);

        $data = [
            'sections' => [
                [
                    'section_type' => PageSectionType::RichText->value,
                    'title' => 'About All the Things Light',
                    'is_enabled' => true,
                    'content_json' => [
                        'body' => $this->allTheThingsLightBody(),
                    ],
                ],
                [
                    'section_type' => PageSectionType::RichText->value,
                    'title' => 'About Cory Gold',
                    'is_enabled' => true,
                    'content_json' => [
                        'body' => '',
                    ],
                ],
                [
                    'section_type' => PageSectionType::RichText->value,
                    'title' => "About Jacob d'IAWARII",
                    'is_enabled' => true,
                    'content_json' => [
                        'body' => '',
                        'video_media_id' => $jacobVideoMediaId,
                    ],
                ],
            ],
        ];

        app(UpdatePageAction::class)->handle($page, $data, $actor);
    }

    private function resolveJacobVideoMediaId(User $actor): ?int
    {
        $existing = Media::query()
            ->where('disk', self::JACOB_VIDEO_DISK)
            ->where('path', self::JACOB_VIDEO_PATH)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        if (! Storage::disk(self::JACOB_VIDEO_DISK)->exists(self::JACOB_VIDEO_PATH)) {
            return null;
        }

        return app(StoreUploadedMediaAction::class)
            ->handle(self::JACOB_VIDEO_DISK, self::JACOB_VIDEO_PATH, $actor, 'protected')
            ->id;
    }

    private function allTheThingsLightBody(): string
    {
        return <<<'HTML'
            <p>All the Things Light is a place to come and gather.</p>
            <p>A gathering place for inspired music, meaningful conversation, reflection, gratitude, and connection—created around a simple belief: there is always light to notice, light to share, and light to become.</p>
            <p>Here, music can carry a message. Conversation can open a new way of seeing. Gratitude can turn attention toward what is already present. A few words can become a Light Post, offering encouragement, hope, or reflection for the gathering.</p>
            <p>All the Things Light is not about having all the answers. It is about making room—to listen, to notice, to create, to reflect, to remember, and to celebrate the light found in everyday life.</p>
            <p>At the heart of it all are a few ideas:</p>
            <p>Love is creation’s greatest idea.</p>
            <p>We are one.</p>
            <p>The closer we move toward the light, the more we begin to look like the light.</p>
            <p>All the Things Light is an invitation to experience those ideas—not only through words, but through music, conversation, gratitude, creativity, and connection.</p>
            <p>There is room here for joy.</p>
            <p>There is room here for reflection.</p>
            <p>There is room here for questions.</p>
            <p>There is room here to simply be.</p>
            <p>Come and Gather. ✨</p>
            HTML;
    }
}
