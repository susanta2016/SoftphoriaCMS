<?php

namespace App\Actions\Page;

use App\Actions\Page\Concerns\SavesPageSeo;
use App\Actions\Page\Concerns\SnapshotsPageRevisions;
use App\Actions\Page\Concerns\SyncsPageSections;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreatePageAction
{
    use SavesPageSeo, SnapshotsPageRevisions, SyncsPageSections;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Page
    {
        return DB::transaction(function () use ($data, $actor): Page {
            $page = new Page;
            $page->fill(collect($data)->except(['sections', 'seo'])->all());
            // The `status` column defaults to 'draft' at the DB level, but
            // that default is invisible to this in-memory instance until a
            // fresh reload — snapshotRevision() reads $page->status
            // immediately after save(), so it must be set explicitly here.
            $page->status ??= PageStatus::Draft;
            $page->author_id = $actor->getKey();
            $page->created_by = $actor->getKey();
            $page->updated_by = $actor->getKey();
            $page->save();

            $this->syncSections($page, $data['sections'] ?? []);
            $this->saveSeo($page, $data['seo'] ?? []);
            $this->snapshotRevision($page, $actor);

            $this->auditLog->record($actor, 'page.created', $page, ['title' => $page->title]);

            return $page;
        });
    }
}
