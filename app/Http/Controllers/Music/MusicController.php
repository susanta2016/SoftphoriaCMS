<?php

namespace App\Http\Controllers\Music;

use App\Enums\PageSectionType;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\Review;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckAlbumReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckTrackReadinessAction;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\SongStory;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Support\DailyListenQuota;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Public Music landing/catalogue + Album/Single listening pages. Fully
 * public once Published — no membership/entitlement gate on viewing,
 * mirroring PoetryProseController's shape (thin controller, published-only,
 * 404 on anything else). Purchase/ownership state is layered on top of this
 * in a later phase; nothing here decides who may buy or download.
 *
 * Hero copy is sourced from an optional "music" CMS Page's Hero section
 * (same mechanism HomeController uses for "home") so it's admin-editable
 * without a Music-specific content model — the defaults below are only the
 * fallback when no such Page exists yet.
 */
class MusicController extends Controller implements Sitemapable
{
    public function index(Request $request, SettingsRepository $settings): View
    {
        $type = $request->string('type')->lower()->value();
        $type = in_array($type, ['album', 'single'], true) ? $type : null;
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->value() === 'oldest' ? 'oldest' : 'newest';
        $filters = ['type' => $type, 'q' => $search, 'sort' => $sort];

        $releases = $this->paginatedCatalogue($type, $search, $sort);

        // The catalogue's filter pills/search/sort/pagination fetch this same
        // route asynchronously (X-Requested-With, set by the fetch() calls in
        // app.js) and swap only this fragment in — no full-page reload, and
        // no need to recompute the hero/featured/song-stories content that
        // never changes across a filter/search/sort/page request.
        if ($request->ajax()) {
            return view('music.partials.catalogue', ['releases' => $releases, 'filters' => $filters]);
        }

        $chrome = $this->siteChrome($settings);
        $page = $this->musicPage();
        $hero = $this->heroContent($page);
        $featured = $this->featuredRelease();
        $songStories = $this->latestSongStories();
        $storyBanner = $this->storyBannerContent($page);

        $seo = SeoTagBuilder::build($page?->seo, [
            'title' => "Music — {$chrome['siteName']}",
            'description' => 'Albums, singles, lyrics, and the stories behind the songs.',
            'canonical' => route('music.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('music.index', [
            ...$chrome,
            'seo' => $seo,
            'hero' => $hero,
            'featured' => $featured,
            'releases' => $releases,
            'songStories' => $songStories,
            'storyBanner' => $storyBanner,
            'filters' => $filters,
        ]);
    }

    public function showAlbum(Album $album, SettingsRepository $settings): View
    {
        abort_unless($album->status === ReleaseStatus::Published, 404);

        $chrome = $this->siteChrome($settings);
        $album->load([
            'cover',
            'streamingLinks',
            'tracks' => fn ($query) => $query->published()->with('lyrics'),
        ]);

        $seo = SeoTagBuilder::build($album->seo, [
            'title' => "{$album->title} — {$chrome['siteName']}",
            'description' => $this->excerpt($album->description) ?? "Listen to {$album->title}.",
            'canonical' => route('music.albums.show', $album),
            'type' => 'music.album',
            'image' => $album->cover,
            'published_at' => $album->release_date,
            'modified_at' => $album->updated_at,
        ], $chrome['general']);

        return view('music.listening', [
            ...$chrome,
            'seo' => $seo,
            'release' => $this->albumViewModel($album),
            'related' => $this->relatedReleases($album->id, 'album'),
            'topBanner' => $this->topBannerMedia(),
        ]);
    }

    public function showSingle(Single $single, SettingsRepository $settings): View
    {
        abort_unless($single->status === ReleaseStatus::Published, 404);

        $chrome = $this->siteChrome($settings);
        $single->load(['cover', 'streamingLinks', 'track.lyrics']);

        $seo = SeoTagBuilder::build($single->seo, [
            'title' => "{$single->title} — {$chrome['siteName']}",
            'description' => $this->excerpt($single->description) ?? "Listen to {$single->title}.",
            'canonical' => route('music.singles.show', $single),
            'type' => 'music.song',
            'image' => $single->cover,
            'published_at' => $single->release_date,
            'modified_at' => $single->updated_at,
        ], $chrome['general']);

        return view('music.listening', [
            ...$chrome,
            'seo' => $seo,
            'release' => $this->singleViewModel($single),
            'related' => $this->relatedReleases($single->id, 'single'),
            'topBanner' => $this->topBannerMedia(),
            ...$this->reviewSummary($single->track),
            ...$this->reactionSummary($single->track),
        ]);
    }

    /**
     * A single track's own listening page — only meaningful for an
     * Album-owned track (a Single-owned track already IS that Single's
     * listening page, so visiting one here 301-redirects to it rather than
     * publishing the same song at two URLs).
     */
    public function showTrack(Track $track, SettingsRepository $settings): View|RedirectResponse
    {
        abort_unless($track->status === TrackStatus::Published, 404);

        if ($track->single_id !== null) {
            $single = $track->single;
            abort_unless($single && $single->status === ReleaseStatus::Published, 404);

            return redirect()->route('music.singles.show', $single, 301);
        }

        $album = $track->album;
        abort_unless($album && $album->status === ReleaseStatus::Published, 404);

        $chrome = $this->siteChrome($settings);
        $track->load(['lyrics', 'songStory', 'credits', 'categories', 'audio']);
        $album->load(['cover', 'streamingLinks']);

        $seo = SeoTagBuilder::build($track->seo, [
            'title' => "{$track->title} — {$chrome['siteName']}",
            'description' => $this->excerpt($track->description) ?? "Listen to {$track->title}.",
            'canonical' => route('music.tracks.show', $track),
            'type' => 'music.song',
            'image' => $album->cover,
            'published_at' => $album->release_date,
            'modified_at' => $track->updated_at,
        ], $chrome['general']);

        return view('music.listening', [
            ...$chrome,
            'seo' => $seo,
            'release' => $this->trackViewModel($track, $album),
            'related' => $this->relatedReleases($album->id, 'album'),
            'topBanner' => $this->topBannerMedia(),
            ...$this->reviewSummary($track),
            ...$this->reactionSummary($track),
        ]);
    }

    /**
     * Both the "/" and "/music" landing pages, and every published Album/
     * Single, contribute their own sitemap entries — this covers only the
     * landing page itself, mirroring HomeController::sitemapEntries().
     * Album/Single are registered separately in config('seo.sitemap_sources').
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection
    {
        $page = Page::query()->published()->where('slug', 'music')->with('seo')->first();

        if ($page?->seo?->isNoindex()) {
            return collect();
        }

        return collect([['loc' => route('music.index'), 'lastmod' => $page?->updated_at ?? now()]]);
    }

    private function musicPage(): ?Page
    {
        return Page::query()
            ->published()
            ->where('slug', 'music')
            ->with([
                'sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order'),
                'seo',
            ])
            ->first();
    }

    /**
     * The same top banner image used on the Music landing page's hero —
     * reused as-is (not a second, per-release banner) behind the top
     * section of every Album/Single/Track listening page, so the whole
     * Music area shares one consistent banner rather than each page
     * needing its own. Null when no "music" Page/Hero section/image is
     * configured yet — the listening page's own background (plain white)
     * is then an equally correct, unbroken fallback.
     */
    private function topBannerMedia(): ?Media
    {
        $mediaId = $this->musicPage()?->sections
            ->firstWhere('section_type', PageSectionType::Hero->value)
            ?->content_json['media_id'] ?? null;

        return $mediaId ? Media::find($mediaId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function heroContent(?Page $page): array
    {
        $defaults = [
            'heading' => 'Songs written in the language of light.',
            'subheading' => "Albums, lyrics, and the stories behind a message.\nListen when you're ready. Listen, then.",
            'media_id' => null,
            'cta_label' => 'Latest Release',
            'cta_url' => '#featured-release',
            'secondary_cta_label' => 'Read Song Stories',
            'secondary_cta_url' => '#song-stories',
        ];

        $content = $page?->sections
            ->firstWhere('section_type', PageSectionType::Hero->value)
            ?->content_json ?? [];

        $merged = [...$defaults, ...array_filter($content, fn (mixed $value): bool => $value !== null && $value !== '')];
        $merged['media'] = $merged['media_id'] ? Media::find($merged['media_id']) : null;

        return $merged;
    }

    /**
     * The "Song Stories" section's background banner — a second Hero-type
     * section on the same "music" CMS Page (the first is the top-of-page
     * hero from heroContent() above). Sections are already eager-loaded
     * ordered by sort_order (musicPage()), so the second Hero-type section
     * in that order is always this one. Only the image is consumed today;
     * a heading/subheading set on that section is available for the admin
     * to fill in later but isn't required — the view falls back to the
     * existing hardcoded "Where the songs came from" copy when absent.
     *
     * @return array{media: ?Media, heading: ?string, subheading: ?string}
     */
    private function storyBannerContent(?Page $page): array
    {
        $heroSections = $page?->sections
            ->where('section_type', PageSectionType::Hero->value)
            ->values() ?? collect();

        $content = $heroSections->get(1)?->content_json ?? [];
        $mediaId = $content['media_id'] ?? null;

        return [
            'media' => $mediaId ? Media::find($mediaId) : null,
            'heading' => $content['heading'] ?? null,
            'subheading' => $content['subheading'] ?? null,
        ];
    }

    private function featuredRelease(): Album|Single|null
    {
        $albums = Album::query()->published()->where('is_featured', true)
            ->select(['id', DB::raw("'album' as release_type"), 'release_date']);

        $singles = Single::query()->published()->where('is_featured', true)
            ->select(['id', DB::raw("'single' as release_type"), 'release_date']);

        $top = $albums->toBase()->unionAll($singles->toBase())
            ->orderByDesc('release_date')
            ->limit(1)
            ->first();

        if (! $top) {
            return null;
        }

        return $top->release_type === 'album'
            ? Album::query()->with(['cover', 'streamingLinks', 'tracks' => fn ($q) => $q->published()->with('lyrics')])->find($top->id)
            : Single::query()->with(['cover', 'streamingLinks', 'track.lyrics'])->find($top->id);
    }

    /**
     * The landing page's "Where the songs came from" section — only real
     * Song Story content (App\Modules\Music\Models\SongStory), never
     * invented copy. A track's Song Story only surfaces once both the
     * track and its parent Album/Single are Published.
     *
     * @return Collection<int, SongStory>
     */
    private function latestSongStories(): Collection
    {
        return SongStory::query()
            ->whereHas('track', fn ($query) => $query->published()->where(
                fn ($q) => $q->whereHas('album', fn ($a) => $a->published())
                    ->orWhereHas('single', fn ($s) => $s->published())
            ))
            ->with(['track.album', 'track.single'])
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    private function paginatedCatalogue(?string $type, string $search, string $sort): LengthAwarePaginator
    {
        $albumsQuery = Album::query()->published()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->select([
                'id', DB::raw("'album' as release_type"), 'public_id', 'title', 'slug',
                'cover_media_id', 'release_date', 'is_featured',
                DB::raw("(select count(*) from tracks where tracks.album_id = albums.id and tracks.status = 'published') as track_count"),
            ]);

        $singlesQuery = Single::query()->published()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->select([
                'id', DB::raw("'single' as release_type"), 'public_id', 'title', 'slug',
                'cover_media_id', 'release_date', 'is_featured',
                DB::raw('1 as track_count'),
            ]);

        $union = match ($type) {
            'album' => $albumsQuery->toBase(),
            'single' => $singlesQuery->toBase(),
            default => $albumsQuery->toBase()->unionAll($singlesQuery->toBase()),
        };

        $direction = $sort === 'oldest' ? 'asc' : 'desc';

        $paginator = DB::query()->fromSub($union, 'releases')
            ->orderBy('release_date', $direction)
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return $this->hydrateCovers($paginator);
    }

    /**
     * @return Collection<int, object>
     */
    private function relatedReleases(int $excludeId, string $excludeType): Collection
    {
        $albumsQuery = Album::query()->published()
            ->when($excludeType === 'album', fn ($q) => $q->where('id', '!=', $excludeId))
            ->select([
                'id', DB::raw("'album' as release_type"), 'public_id', 'title', 'slug',
                'cover_media_id', 'release_date',
                DB::raw("(select count(*) from tracks where tracks.album_id = albums.id and tracks.status = 'published') as track_count"),
            ]);

        $singlesQuery = Single::query()->published()
            ->when($excludeType === 'single', fn ($q) => $q->where('id', '!=', $excludeId))
            ->select([
                'id', DB::raw("'single' as release_type"), 'public_id', 'title', 'slug',
                'cover_media_id', 'release_date',
                DB::raw('1 as track_count'),
            ]);

        $rows = $albumsQuery->toBase()->unionAll($singlesQuery->toBase())
            ->orderByDesc('release_date')
            ->limit(4)
            ->get();

        return $this->attachCovers($rows);
    }

    private function hydrateCovers(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $paginator->setCollection($this->attachCovers($paginator->getCollection()));

        return $paginator;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function attachCovers(Collection $rows): Collection
    {
        $coverIds = $rows->pluck('cover_media_id')->filter()->unique();
        $covers = Media::query()->whereIn('id', $coverIds)->get()->keyBy('id');

        return $rows->map(function (object $row) use ($covers) {
            $row->cover = $covers->get($row->cover_media_id);
            $row->release_date = $row->release_date ? Carbon::parse($row->release_date) : null;

            return $row;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function albumViewModel(Album $album): array
    {
        return [
            'type' => 'album',
            'model' => $album,
            'title' => $album->title,
            'description' => $album->description,
            'release_date' => $album->release_date,
            'cover' => $album->cover,
            // The Album video field is hidden in the admin form (client
            // decision — see AlbumForm.php) so it can't be maintained going
            // forward; the frontend never shows a Video section for Albums,
            // even if a legacy value happens to still be stored.
            'embed_video_url' => null,
            'streaming_links' => $album->streamingLinks,
            'tracks' => $album->tracks,
            'total_duration_seconds' => $album->tracks->sum('duration_seconds'),
            'show_route' => route('music.albums.show', $album),
            'stream_track' => $album->tracks->first(),
            'parent_label' => null,
            'parent_route' => null,
            'purchase' => $this->purchaseState($album),
            'purchase_type' => 'album',
            'purchase_slug' => $album->slug,
            'parent_purchase' => null,
            'parent_purchase_type' => null,
            'parent_purchase_slug' => null,
            'listening' => $this->listeningAccessState(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function singleViewModel(Single $single): array
    {
        $track = $single->track;

        return [
            'type' => 'single',
            'model' => $single,
            'title' => $single->title,
            'description' => $single->description,
            'release_date' => $single->release_date,
            'cover' => $single->cover,
            'embed_video_url' => $track?->video_embed_url,
            'streaming_links' => $single->streamingLinks,
            'tracks' => $track ? collect([$track]) : collect(),
            'total_duration_seconds' => $track?->duration_seconds ?? 0,
            'show_route' => route('music.singles.show', $single),
            'stream_track' => $track,
            'parent_label' => null,
            'parent_route' => null,
            'purchase' => $this->purchaseState($single),
            'purchase_type' => 'single',
            'purchase_slug' => $single->slug,
            'parent_purchase' => null,
            'parent_purchase_type' => null,
            'parent_purchase_slug' => null,
            'listening' => $this->listeningAccessState(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trackViewModel(Track $track, Album $album): array
    {
        return [
            'type' => 'track',
            'model' => $track,
            'title' => $track->title,
            'description' => $track->description,
            'release_date' => $album->release_date,
            'cover' => $album->cover,
            'embed_video_url' => $track->video_embed_url,
            'streaming_links' => $album->streamingLinks,
            'tracks' => collect([$track]),
            'total_duration_seconds' => $track->duration_seconds,
            'show_route' => route('music.tracks.show', $track),
            'stream_track' => $track,
            'parent_label' => $album->title,
            'parent_route' => route('music.albums.show', $album),
            // An Album-owned Track is independently purchasable on its own
            // page at the global per-song price (GlobalPricingResolver::
            // perSongPrice()) — never the parent Album's price, and buying it
            // never grants the rest of the Album. See trackPurchaseState().
            'purchase' => $this->trackPurchaseState($track),
            'purchase_type' => 'track',
            'purchase_slug' => $track->slug,
            'parent_purchase' => null,
            'parent_purchase_type' => null,
            'parent_purchase_slug' => null,
            'listening' => $this->listeningAccessState(),
        ];
    }

    /**
     * Guest/registered playback-access state, shared by every listening
     * page's view model — see TrackStreamController, which enforces the
     * same two limits server-side; this is only what the page needs to
     * decide, per track row, whether to render a playable source at all and
     * which message to show when it can't.
     *
     * @return array{is_guest: bool, guest_limit_seconds: int, daily_limit: int, daily_limit_reached: bool}
     */
    private function listeningAccessState(): array
    {
        $user = Auth::user();
        $quota = $user !== null
            ? app(DailyListenQuota::class)->check($user->id)
            : ['limit' => (int) config('features.registered_user_whole_song_listens_per_day'), 'reached' => false];

        return [
            'is_guest' => $user === null,
            'guest_limit_seconds' => (int) config('features.guest_user_listening_limit_seconds'),
            'daily_limit' => $quota['limit'],
            'daily_limit_reached' => $quota['reached'],
        ];
    }

    /**
     * The Buy button's state on a listening page — never invents a new
     * entitlement rule: an active Pro subscriber gets "included" (catalogue-
     * wide access, per Subscription::isActive()'s docblock), an owner of a
     * real Entitlement row gets "owned", an item that fails the existing
     * purchase-readiness check is hidden entirely rather than shown broken,
     * and everyone else (guest, registered non-owner) gets a real "buy" with
     * the current Global Pricing amount.
     *
     * @return array{state: 'buy'|'owned'|'included'|'not_ready', price: ?float}
     */
    private function purchaseState(Album|Single $item): array
    {
        $user = Auth::user();

        if ($user?->hasActiveMembership()) {
            return ['state' => 'included', 'price' => null];
        }

        if ($user?->ownsRelease($item)) {
            return ['state' => 'owned', 'price' => null];
        }

        $readiness = $item instanceof Album
            ? app(CheckAlbumReadinessAction::class)->handle($item)
            : app(CheckSingleReadinessAction::class)->handle($item);

        if (! $readiness->ready) {
            return ['state' => 'not_ready', 'price' => null];
        }

        $pricing = app(GlobalPricingResolver::class);
        $price = $item instanceof Album ? $pricing->fullAlbumPrice() : $pricing->perSongPrice();

        return ['state' => 'buy', 'price' => (float) $price];
    }

    /**
     * The individual Track's own Buy button state — always priced at
     * GlobalPricingResolver::perSongPrice(), the exact same global Single/
     * Track price a Single already uses, regardless of whether this Track
     * belongs to an Album. The Track's parent Album relationship has zero
     * effect on this price or on the entitlement a purchase here grants
     * (see CreatePendingOrderAction/User::ownsTrack()) — mirrors
     * purchaseState() exactly, one purchasable type at a time.
     *
     * @return array{state: 'buy'|'owned'|'included'|'not_ready', price: ?float}
     */
    private function trackPurchaseState(Track $track): array
    {
        $user = Auth::user();

        if ($user?->hasActiveMembership()) {
            return ['state' => 'included', 'price' => null];
        }

        if ($user?->ownsTrack($track)) {
            return ['state' => 'owned', 'price' => null];
        }

        $readiness = app(CheckTrackReadinessAction::class)->handle($track);

        if (! $readiness->ready) {
            return ['state' => 'not_ready', 'price' => null];
        }

        $price = app(GlobalPricingResolver::class)->perSongPrice();

        return ['state' => 'buy', 'price' => (float) $price];
    }

    /**
     * Comments belong to the individual Track — never the Album — so only
     * showSingle()/showTrack() call this (a Single's "track" is its one
     * underlying Track row; see Single::track()). Mirrors
     * PodcastController::show()'s inline computation exactly, reusing the
     * same App\Models\Review::scopeApproved() so an unapproved comment can
     * never appear publicly. No more star rating/average — client-confirmed
     * reversal, 2026-09-02 (see App\Actions\Review\SubmitReviewAction).
     *
     * @return array{reviews: Collection<int, Review>, reviewCount: int}
     */
    private function reviewSummary(?Track $track): array
    {
        if (! $track) {
            return ['reviews' => collect(), 'reviewCount' => 0];
        }

        $reviews = $track->reviews()->approved()->with('user.profile.avatar')->latest()->get();

        return [
            'reviews' => $reviews,
            'reviewCount' => $reviews->count(),
        ];
    }

    /**
     * The separate 🙌 reaction (client-confirmed, 2026-09-02) — never
     * moderated, so unlike reviewSummary() this counts every row, not just
     * an "approved" subset (App\Models\Reaction has no status column at
     * all). `userReacted` drives the button's pressed/unpressed state for
     * the current visitor; always false for a guest.
     *
     * @return array{reactionCount: int, userReacted: bool}
     */
    private function reactionSummary(?Track $track): array
    {
        if (! $track) {
            return ['reactionCount' => 0, 'userReacted' => false];
        }

        return [
            'reactionCount' => $track->reactions()->count(),
            'userReacted' => Auth::check() && $track->reactions()->where('user_id', Auth::id())->exists(),
        ];
    }

    private function excerpt(?string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        return str($text)->stripTags()->limit(160)->toString();
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
