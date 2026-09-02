<?php

namespace App\Http\Controllers;

use App\Models\LightPost;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;

/**
 * A single public Light Post's own minimal detail page — added only because
 * unified Search (App\Modules\Search) needs a real canonical URL to link a
 * Light Post result to; before this, is_public Light Posts had no detail
 * page at all (see LightPost's own docblock and HomeController::
 * latestLightPosts(), the only place they were ever shown). Deliberately
 * bare: no comments/reactions/related-posts — those belong to Track/
 * PodcastEpisode/PoetryProse, never to a Light Post (client-confirmed,
 * 2026-09-02: a Light Post is not a Community content type with that kind
 * of page). Read-only — the submission flow (CreatesLightPostOnRegistration)
 * is untouched.
 *
 * ROBOTS_NOINDEX rather than Sitemapable: see config/seo.php's own comment,
 * which names Light Post explicitly as content that must not be registered
 * for organic indexing merely because it now has a database row and a URL.
 * Still fully public/reachable — indexing and reachability are different
 * questions, and only the former is "no" here.
 */
class LightPostController extends Controller
{
    public function show(LightPost $lightPost, SettingsRepository $settings): View
    {
        abort_unless($lightPost->is_public, 404);

        $lightPost->load('user');
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build($lightPost->seo, [
            'title' => "{$lightPost->searchResultTitle()} — {$chrome['siteName']}",
            'description' => $lightPost->searchResultExcerpt(),
            'canonical' => route('light-posts.show', $lightPost),
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
            'type' => 'article',
            'published_at' => $lightPost->created_at,
            'modified_at' => $lightPost->updated_at,
        ], $chrome['general']);

        return view('light-posts.show', [
            ...$chrome,
            'seo' => $seo,
            'lightPost' => $lightPost,
        ]);
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
