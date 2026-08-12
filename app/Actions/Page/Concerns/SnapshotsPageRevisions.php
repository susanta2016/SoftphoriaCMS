<?php

namespace App\Actions\Page\Concerns;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\User;

/**
 * Shared by CreatePageAction/UpdatePageAction/RestorePageRevisionAction —
 * writes a page_revisions snapshot capturing the page's own attributes plus
 * its current sections (Database Specification §18.2: "page_revisions
 * stores snapshots so editors can review or restore previous page
 * versions"). Snapshot + restore only, per the approved Phase-1 scope — no
 * diff/compare UI.
 */
trait SnapshotsPageRevisions
{
    protected function snapshotRevision(Page $page, User $actor): void
    {
        $page->load('sections');

        $version = (int) $page->revisions()->max('version') + 1;

        // PageRevision declares no #[Fillable] surface (same as AuditLog —
        // see AuditLogService), so attributes are set individually rather
        // than mass-assigned via create().
        $revision = new PageRevision;
        $revision->page_id = $page->id;
        $revision->version = $version;
        $revision->snapshot_json = [
            'title' => $page->title,
            'slug' => $page->slug,
            'template' => $page->template->value,
            'status' => $page->status->value,
            'summary' => $page->summary,
            'featured_image_id' => $page->featured_image_id,
            'publish_at' => $page->publish_at?->toIso8601String(),
            'sections' => $page->sections->map(fn (PageSection $section): array => [
                'section_type' => $section->section_type,
                'title' => $section->title,
                'sort_order' => $section->sort_order,
                'is_enabled' => $section->is_enabled,
                'settings_json' => $section->settings_json,
                'content_json' => $section->content_json,
            ])->all(),
        ];
        $revision->created_by = $actor->getKey();
        $revision->save();
    }
}
