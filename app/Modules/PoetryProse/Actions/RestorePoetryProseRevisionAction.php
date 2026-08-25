<?php

namespace App\Modules\PoetryProse\Actions;

use App\Models\User;
use App\Modules\PoetryProse\Actions\Concerns\SnapshotsPoetryProseRevisions;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Modules\PoetryProse\Models\PoetryProseRevision;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class RestorePoetryProseRevisionAction
{
    use SnapshotsPoetryProseRevisions;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(PoetryProse $entry, PoetryProseRevision $revision, User $actor): PoetryProse
    {
        return DB::transaction(function () use ($entry, $revision, $actor): PoetryProse {
            $snapshot = $revision->snapshot_json;

            $entry->title = $snapshot['title'];
            $entry->slug = $snapshot['slug'];
            $entry->body = $snapshot['body'];
            $entry->content_type = $snapshot['content_type'];
            $entry->status = $snapshot['status'];
            $entry->collection_id = $snapshot['collection_id'] ?? null;
            $entry->featured_image_id = $snapshot['featured_image_id'];
            $entry->author_id = $snapshot['author_id'];
            $entry->publish_at = $snapshot['publish_at'];
            $entry->updated_by = $actor->getKey();
            $entry->save();

            // Restoring is itself a new revision — never rewinds the
            // version counter, so the restore is visible in the history
            // rather than silently replacing it.
            $this->snapshotRevision($entry, $actor);

            $this->auditLog->record($actor, 'poetry_prose.revision_restored', $entry, [
                'title' => $entry->title,
                'restored_version' => $revision->version,
            ]);

            return $entry;
        });
    }
}
