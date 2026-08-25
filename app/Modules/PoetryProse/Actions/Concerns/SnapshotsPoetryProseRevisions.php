<?php

namespace App\Modules\PoetryProse\Actions\Concerns;

use App\Models\User;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Modules\PoetryProse\Models\PoetryProseRevision;

/**
 * Shared by CreatePoetryProseAction/UpdatePoetryProseAction/
 * RestorePoetryProseRevisionAction — writes a poetry_prose_revisions
 * snapshot capturing the entry's own attributes, mirroring
 * SnapshotsPageRevisions exactly (same table shape: version + snapshot_json
 * + created_by, no updated_at). Snapshot + restore only — no diff/compare
 * UI, matching Pages' own Phase-1 scope.
 */
trait SnapshotsPoetryProseRevisions
{
    protected function snapshotRevision(PoetryProse $entry, User $actor): void
    {
        $version = (int) $entry->revisions()->max('version') + 1;

        $revision = new PoetryProseRevision;
        $revision->poetry_prose_id = $entry->id;
        $revision->version = $version;
        $revision->snapshot_json = [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'body' => $entry->body,
            'content_type' => $entry->content_type->value,
            'status' => $entry->status->value,
            'collection_id' => $entry->collection_id,
            'featured_image_id' => $entry->featured_image_id,
            'author_id' => $entry->author_id,
            'publish_at' => $entry->publish_at?->toIso8601String(),
        ];
        $revision->created_by = $actor->getKey();
        $revision->save();
    }
}
