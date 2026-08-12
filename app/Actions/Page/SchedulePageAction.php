<?php

namespace App\Actions\Page;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Carbon;

class SchedulePageAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Page $page, Carbon $publishAt, User $actor): Page
    {
        $page->status = PageStatus::Scheduled;
        $page->publish_at = $publishAt;
        $page->updated_by = $actor->getKey();
        $page->save();

        $this->auditLog->record($actor, 'page.scheduled', $page, [
            'title' => $page->title,
            'publish_at' => $publishAt->toIso8601String(),
        ]);

        return $page;
    }
}
