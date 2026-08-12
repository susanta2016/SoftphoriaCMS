<?php

namespace App\Actions\Page;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * $actor is nullable to also serve PublishDuePagesCommand — the scheduler
 * publishing a due page isn't attributable to any admin.
 */
class PublishPageAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Page $page, ?User $actor): Page
    {
        $page->status = PageStatus::Published;
        $page->publish_at ??= now();
        $page->updated_by = $actor?->getKey();
        $page->save();

        $this->auditLog->record($actor, 'page.published', $page, ['title' => $page->title]);

        return $page;
    }
}
