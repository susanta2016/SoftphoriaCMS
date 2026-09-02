<?php

namespace App\Modules\InspirationalResources\Models;

use App\Models\SeoMetadata;
use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Shared\Support\Search\SearchResultRepresentable;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * A submission from the public "Inspirational Resources" form
 * (database/migrations/2026_08_10_100903_create_resource_submissions_table.php).
 * `status` is a review-queue state only: Submitted → In Review → Approved →
 * Archived, with no editorial conversion or relation to any other module
 * (the earlier "Create Poetry/Prose Draft" conversion path was removed
 * 2026-09-02; client-confirmed this module never relates to Poetry/Prose).
 * `inspirational_resource_id` stays a real column on this table (part of
 * the pre-existing migrated schema) but is never read or written by any
 * application code. `reference_url` (2026-09-02) replaced the old
 * related_album_id/related_track_id pickers — a submitter can point to any
 * outside source, not just an in-catalogue Album/Track.
 *
 * **REVERSED 2026-09-02:** an Approved submission is now genuinely public —
 * it gets its own listing entry and detail page (`slug`, added the same
 * day), mirroring Poetry/Prose's public pages. Everything before Approved
 * (Submitted/InReview) and Archived stays a private administrative record,
 * same as before.
 */
#[Fillable([
    'user_id', 'name', 'email', 'subject', 'category', 'message',
    'reference_url', 'status', 'slug',
])]
class ResourceSubmission extends Model implements SearchResultRepresentable, Sitemapable
{
    use Searchable;

    protected function casts(): array
    {
        return [
            'status' => ResourceSubmissionStatus::class,
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

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ResourceSubmissionStatus::Approved);
    }

    /**
     * Plain-text LIKE search across subject and message, same convention as
     * PoetryProse::scopeSearch().
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('subject', 'like', "%{$term}%")
            ->orWhere('message', 'like', "%{$term}%"));
    }

    /**
     * Not every submission has a Subject — falls back to attributing it to
     * the submitter's name so the public listing/detail page never shows a
     * blank title.
     */
    public function publicTitle(): string
    {
        return $this->subject ?: "A Story from {$this->name}";
    }

    public function excerpt(int $length = 160): string
    {
        return str($this->message)->limit($length)->toString();
    }

    /**
     * Mirrors PoetryProse::sitemapEntries() exactly — approved scope, then
     * reject noindex/canonical-elsewhere, then map to loc/lastmod.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection
    {
        return static::query()
            ->approved()
            ->with('seo')
            ->orderBy('slug')
            ->get()
            ->reject(fn (self $submission): bool => ($submission->seo?->isNoindex() ?? false)
                || ($submission->seo?->canonicalPointsElsewhere(route('inspirational-resources.show', $submission)) ?? false))
            ->map(fn (self $submission): array => [
                'loc' => route('inspirational-resources.show', $submission),
                'lastmod' => $submission->updated_at,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'subject' => $this->subject,
            'message' => $this->message,
        ];
    }

    /**
     * See Track::newScoutQuery()'s docblock — this is the real enforcement
     * point for Scout's "database" driver, not shouldBeSearchable() below.
     */
    public function newScoutQuery(ScoutBuilder $builder): Builder
    {
        return static::query()->approved();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ResourceSubmissionStatus::Approved;
    }

    public function searchResultType(): string
    {
        return 'Inspirational Resource';
    }

    public function searchResultTitle(): string
    {
        return $this->publicTitle();
    }

    public function searchResultExcerpt(): string
    {
        return $this->excerpt();
    }

    /**
     * No Media relation exists on this model — a submission has no image.
     */
    public function searchResultImageUrl(): ?string
    {
        return null;
    }

    public function searchResultUrl(): string
    {
        return route('inspirational-resources.show', $this);
    }
}
