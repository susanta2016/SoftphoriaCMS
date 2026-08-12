<?php

namespace App\Exceptions\Page;

use App\Models\Page;
use RuntimeException;

/**
 * Thrown by DeletePageAction when an enabled navigation menu item still
 * references this page. The database's restrictOnDelete() on
 * menu_items.page_id only guards a real forceDelete(); Pages are normally
 * soft-deleted, which never touches that constraint, so this is the
 * domain-layer equivalent guard for the soft-delete path (ADMIN-006 §H).
 */
class PageInUseByNavigationException extends RuntimeException
{
    public static function forPage(Page $page): self
    {
        return new self("\"{$page->title}\" is still used by a navigation menu. Remove it from navigation before deleting the page.");
    }
}
