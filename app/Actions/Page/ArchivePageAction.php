<?php

namespace App\Actions\Page;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\AuditLogService;

class ArchivePageAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Page $page, User $actor): Page
    {
        $page->status = PageStatus::Archived;
        $page->updated_by = $actor->getKey();
        $page->save();

        $this->auditLog->record($actor, 'page.archived', $page, ['title' => $page->title]);

        return $page;
    }
}
