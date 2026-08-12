<?php

namespace App\Console\Commands;

use App\Actions\Page\PublishPageAction;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Flips Scheduled pages to Published once their publish_at has passed
 * (ADMIN-006 §N.1) — pages.status doesn't change itself just because
 * publish_at elapsed; something has to actually do the transition.
 * Registered against the Laravel scheduler in bootstrap/app.php.
 */
class PublishDuePagesCommand extends Command
{
    protected $signature = 'pages:publish-due';

    protected $description = 'Publish scheduled pages whose publish_at has passed';

    public function handle(PublishPageAction $publishPage): int
    {
        /** @var User|null $systemActor */
        $systemActor = null;

        $duePages = Page::query()
            ->where('status', PageStatus::Scheduled)
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($duePages as $page) {
            $publishPage->handle($page, $systemActor);
        }

        $this->info("Published {$duePages->count()} due page(s).");

        return self::SUCCESS;
    }
}
