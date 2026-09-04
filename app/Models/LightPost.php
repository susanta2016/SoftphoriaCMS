<?php

namespace App\Models;

use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\Concerns\HasPublicId;
use App\Shared\Support\Search\SearchResultRepresentable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * A member-authored short message — either the registration page's "Leave a
 * Little Light ✨" prompt (RegisterFreeUserAction/RegisterProUserAction, via
 * CreatesLightPostOnRegistration) or a Gratitude Journal entry
 * (App\Actions\GratitudeJournal), distinguished by `source`
 * (App\Enums\LightPostSource; Gratitude Journal audit §3/§13 — reuses this
 * table rather than a second one). Visibility is a three-state enum
 * (App\Enums\GratitudeJournalVisibility — Gratitude Journal three-state
 * visibility change, 2026-09-05, replacing the previous is_public boolean):
 * Public (homepage), Private (owner only), Community (the shared member
 * feed). A registration post is always Public. A public *registration* post
 * also has its own minimal detail page (LightPostController@show,
 * light-posts.show) — added only so unified Search (see App\Modules\Search)
 * has a canonical URL to link to; it is deliberately NOT registered in
 * config('seo.sitemap_sources') (see that config file's own comment, which
 * names Light Post and Gratitude Journal explicitly) — pages here are
 * reachable directly and via Search, not promoted for organic indexing,
 * hence ROBOTS_NOINDEX in LightPostController rather than Sitemapable. A
 * Gratitude Journal entry has NO detail page and is NOT searchable at all —
 * see newScoutQuery()/shouldBeSearchable() and LightPostController::show()
 * below — its only public surfaces are the homepage feed (Public) and the
 * shared member feed (Community).
 */
#[Fillable(['user_id', 'source', 'content', 'visibility'])]
class LightPost extends Model implements SearchResultRepresentable
{
    use HasPublicId, Searchable;

    protected function casts(): array
    {
        return [
            'source' => LightPostSource::class,
            'visibility' => GratitudeJournalVisibility::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', GratitudeJournalVisibility::Public);
    }

    /**
     * Owner-only — never shown on the homepage, the shared member feed,
     * search, or anywhere besides the owner's own Account "Your Entries".
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('visibility', GratitudeJournalVisibility::Private);
    }

    /**
     * The shared member feed's own visibility state — exactly the behavior
     * the old is_public = false ("Private") state already had. Only a
     * Gratitude Journal entry can ever carry this value.
     */
    public function scopeCommunity(Builder $query): Builder
    {
        return $query->where('visibility', GratitudeJournalVisibility::Community);
    }

    /**
     * Journal-only entries — used by the account-area Gratitude Journal
     * controller to scope a member to their own journal rows, never their
     * registration post.
     */
    public function scopeJournal(Builder $query): Builder
    {
        return $query->where('source', LightPostSource::Journal);
    }

    /**
     * Registration-only entries — the sole set of rows still eligible for
     * Search indexing and the /light-posts/{id} detail page (see
     * newScoutQuery()/shouldBeSearchable() and LightPostController::show()).
     */
    public function scopeRegistrationSourced(Builder $query): Builder
    {
        return $query->where('source', LightPostSource::Registration);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }

    /**
     * Laravel Scout's "database" engine (config('scout.driver')) never
     * consults shouldBeSearchable() — it runs a live query against this
     * method's return value instead (see Laravel\Scout\Engines\
     * DatabaseEngine::newSearchQuery()), so this is the real enforcement
     * point keeping a private Light Post out of search results, not the
     * shouldBeSearchable() override below. Both are kept in sync so nothing
     * changes if the driver is ever swapped for a real remote index later.
     *
     * Also excludes Gratitude Journal entries (registrationSourced(), not
     * just public()) — Journal entries must never become individually
     * searchable documents, per the Gratitude Journal audit §6/§7, even
     * when public. Registration posts keep their exact prior behavior.
     */
    public function newScoutQuery(ScoutBuilder $builder): Builder
    {
        return static::query()->public()->registrationSourced();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->visibility === GratitudeJournalVisibility::Public && $this->source === LightPostSource::Registration;
    }

    public function searchResultType(): string
    {
        return 'Community';
    }

    /**
     * A Light Post has no title of its own (see the migration's docblock:
     * "member-authored short public message") — falls back to attributing
     * it to the author, the same "no blank title" convention
     * ResourceSubmission::publicTitle() already uses.
     */
    public function searchResultTitle(): string
    {
        return $this->user?->name ? "A Little Light from {$this->user->name}" : 'A Little Light';
    }

    public function searchResultExcerpt(): string
    {
        return str($this->content)->limit(160)->toString();
    }

    /**
     * No image on this model — a Light Post is text-only.
     */
    public function searchResultImageUrl(): ?string
    {
        return null;
    }

    public function searchResultUrl(): string
    {
        return route('light-posts.show', $this);
    }
}
