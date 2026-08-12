<?php

namespace App\Actions\Page;

use App\Actions\Page\Concerns\SavesPageSeo;
use App\Actions\Page\Concerns\SnapshotsPageRevisions;
use App\Actions\Page\Concerns\SyncsPageSections;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Side effects of an ordinary page edit that are easy to forget because
 * they aren't a separate visible action: writing a page_revisions
 * snapshot, and — when the slug changes — inserting a page_redirects row
 * for the old slug (Database Specification §18.2: "page_redirects supports
 * safe slug changes"). Both happen here, in one transaction, rather than
 * being left to Filament's default save.
 */
class UpdatePageAction
{
    use SavesPageSeo, SnapshotsPageRevisions, SyncsPageSections;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Page $page, array $data, User $actor): Page
    {
        return DB::transaction(function () use ($page, $data, $actor): Page {
            $oldSlug = $page->slug;

            $page->fill(collect($data)->except(['sections', 'seo'])->all());
            $page->updated_by = $actor->getKey();
            $page->save();

            if ($page->wasChanged('slug') && $oldSlug !== null) {
                $page->redirects()->create([
                    'old_path' => $oldSlug,
                    'redirect_type' => '301',
                    'is_active' => true,
                ]);
            }

            $this->syncSections($page, $data['sections'] ?? []);
            $this->saveSeo($page, $data['seo'] ?? []);
            $this->snapshotRevision($page, $actor);

            $this->auditLog->record($actor, 'page.updated', $page, ['title' => $page->title]);

            return $page;
        });
    }
}
