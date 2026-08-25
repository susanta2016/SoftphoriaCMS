<?php

namespace App\Modules\InspirationalResources\Models;

use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Track;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw, private submission from the public "Inspirational Resources" form
 * (database/migrations/2026_08_10_100903_create_resource_submissions_table.php)
 * — always an administrative record, never rendered at a public URL, never
 * Sitemapable. `status` is a review-queue state only. Client-confirmed
 * final workflow: the only outcome of an Approved submission is optionally
 * drafting it into Poetry/Prose (see CreatePoetryProseFromSubmissionAction)
 * — there is no separate "publish this submission as its own public
 * resource" step. `inspirational_resource_id` stays a real column on this
 * table (part of the pre-existing migrated schema) but is never read or
 * written by any application code.
 */
#[Fillable([
    'user_id', 'name', 'email', 'subject', 'category', 'message',
    'related_album_id', 'related_track_id', 'status',
])]
class ResourceSubmission extends Model
{
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

    public function relatedAlbum(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'related_album_id');
    }

    public function relatedTrack(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'related_track_id');
    }

    /**
     * What this submission was drafted into, if an admin ran
     * CreatePoetryProseFromSubmissionAction.
     */
    public function poetryProse(): BelongsTo
    {
        return $this->belongsTo(PoetryProse::class);
    }
}
