<?php

namespace App\Actions\Page;

use App\Actions\Page\Concerns\SnapshotsPageRevisions;
use App\Actions\Page\Concerns\SyncsPageSections;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class RestorePageRevisionAction
{
    use SnapshotsPageRevisions, SyncsPageSections;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Page $page, PageRevision $revision, User $actor): Page
    {
        return DB::transaction(function () use ($page, $revision, $actor): Page {
            $snapshot = $revision->snapshot_json;

            $page->title = $snapshot['title'];
            $page->slug = $snapshot['slug'];
            $page->template = $snapshot['template'];
            $page->status = $snapshot['status'];
            $page->summary = $snapshot['summary'];
            $page->featured_image_id = $snapshot['featured_image_id'];
            $page->publish_at = $snapshot['publish_at'];
            $page->updated_by = $actor->getKey();
            $page->save();

            $this->syncSections($page, $snapshot['sections']);

            // Restoring is itself a new revision — never rewinds the
            // version counter, so the restore is visible in the history
            // rather than silently replacing it.
            $this->snapshotRevision($page, $actor);

            $this->auditLog->record($actor, 'page.revision_restored', $page, [
                'title' => $page->title,
                'restored_version' => $revision->version,
            ]);

            return $page;
        });
    }
}
