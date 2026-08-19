<?php

namespace App\Actions\Media\Concerns;

use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Checks every known place a Media row can be referenced — matches the
 * restrictOnDelete() foreign keys already declared on these tables in their
 * migrations (see each table's create migration), which only ever guard a
 * real forceDelete() and never fire on Media's normal soft-delete. This is
 * the single source of truth for "is this media in use" so DeleteMediaAction
 * and its bulk-delete counterpart share one check instead of drifting.
 */
trait FindsMediaUsage
{
    /**
     * @return array<int, string> human-readable usage descriptions, empty if unused
     */
    protected function findMediaUsage(Media $media): array
    {
        $id = $media->id;
        $usedBy = [];

        $foreignKeyChecks = [
            ['table' => 'user_profiles', 'column' => 'avatar_media_id', 'label' => "a user's avatar"],
            ['table' => 'seo_metadata', 'column' => 'og_image_media_id', 'label' => 'an SEO Open Graph image'],
            ['table' => 'seo_metadata', 'column' => 'twitter_image_media_id', 'label' => 'an SEO Twitter image'],
            ['table' => 'user_downloads', 'column' => 'media_id', 'label' => 'a user download'],
            ['table' => 'pages', 'column' => 'featured_image_id', 'label' => "a page's featured image"],
            ['table' => 'singles', 'column' => 'cover_media_id', 'label' => "a single's cover art"],
            ['table' => 'albums', 'column' => 'cover_media_id', 'label' => "an album's cover art"],
            ['table' => 'song_stories', 'column' => 'media_id', 'label' => 'a song story'],
            ['table' => 'podcasts', 'column' => 'artwork_media_id', 'label' => "a podcast's artwork"],
            ['table' => 'podcast_episodes', 'column' => 'artwork_media_id', 'label' => "a podcast episode's artwork"],
            ['table' => 'poetry_prose', 'column' => 'featured_image_id', 'label' => "a poetry/prose entry's featured image"],
            ['table' => 'social_links', 'column' => 'icon_media_id', 'label' => 'a social media icon'],
            ['table' => 'community_attachments', 'column' => 'media_id', 'label' => 'a community attachment'],
        ];

        foreach ($foreignKeyChecks as $check) {
            if (DB::table($check['table'])->where($check['column'], $id)->exists()) {
                $usedBy[] = $check['label'];
            }
        }

        $settingChecks = [
            ['group' => 'general', 'key' => 'logo_media_id', 'label' => 'the site logo'],
            ['group' => 'general', 'key' => 'favicon_media_id', 'label' => 'the site favicon'],
            ['group' => 'footer', 'key' => 'logo_media_id', 'label' => 'the footer logo'],
            ['group' => 'footer', 'key' => 'background_media_id', 'label' => 'the footer background image'],
        ];

        foreach ($settingChecks as $check) {
            $exists = DB::table('settings')
                ->where('group', $check['group'])
                ->where('key', $check['key'])
                ->where('value', (string) $id)
                ->exists();

            if ($exists) {
                $usedBy[] = $check['label'];
            }
        }

        if ($this->jsonColumnsReferenceMedia('page_sections', ['content_json'], $id)) {
            $usedBy[] = 'a page section (hero, gallery, or content block)';
        }

        if ($this->jsonColumnsReferenceMedia('homepage_settings', ['hero_json', 'sections_json'], $id)) {
            $usedBy[] = 'the homepage configuration';
        }

        return $usedBy;
    }

    /**
     * A plain-string LIKE match would false-positive on any number sharing
     * digits, so each row's JSON is decoded and walked recursively instead,
     * checking whether $id appears as a scalar value anywhere in it — the
     * shape of content_json varies per section type (hero.media_id,
     * gallery.media_ids array, image_text.media_id, ...) so this checks the
     * value generically rather than hardcoding every possible key.
     *
     * @param  array<int, string>  $columns
     */
    private function jsonColumnsReferenceMedia(string $table, array $columns, int $id): bool
    {
        $rows = DB::table($table)->select($columns)->get();

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $decoded = json_decode($row->{$column} ?? '', true);

                if (is_array($decoded) && $this->arrayContainsValue($decoded, $id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function arrayContainsValue(array $data, int $id): bool
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                if ($this->arrayContainsValue($value, $id)) {
                    return true;
                }
            } elseif (is_int($value) && $value === $id) {
                return true;
            } elseif (is_string($value) && ctype_digit($value) && (int) $value === $id) {
                return true;
            }
        }

        return false;
    }
}
