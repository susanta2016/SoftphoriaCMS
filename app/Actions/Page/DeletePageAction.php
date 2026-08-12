<?php

namespace App\Actions\Page;

use App\Enums\MenuItemDestinationType;
use App\Exceptions\Page\PageInUseByNavigationException;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * Soft-delete only (ARCHITECTURE.md §13: no hard deletes) — never
 * forceDelete(). menu_items.page_id's restrictOnDelete() only guards a real
 * DELETE, which a soft-delete never issues, so this action is the only
 * thing actually protecting a page still referenced by navigation from
 * disappearing out from under that link. Blocks on *any* referencing menu
 * item, not only enabled ones — a disabled item still holds a stale
 * page_id once the page is gone, and Page's SoftDeletes global scope would
 * make that reference silently resolve to null rather than surface the
 * problem, so a soft-deleted page must never remain a navigation
 * destination at all, active or not.
 */
class DeletePageAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Page $page, User $actor): void
    {
        $referencedByNavigation = MenuItem::query()
            ->where('destination_type', MenuItemDestinationType::Page)
            ->where('page_id', $page->id)
            ->exists();

        if ($referencedByNavigation) {
            throw PageInUseByNavigationException::forPage($page);
        }

        $page->delete();

        $this->auditLog->record($actor, 'page.deleted', $page, ['title' => $page->title]);
    }
}
