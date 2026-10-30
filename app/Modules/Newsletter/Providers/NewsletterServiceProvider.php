<?php

namespace App\Modules\Newsletter\Providers;

use App\Modules\Newsletter\Console\Commands\DispatchDueCampaignsCommand;
use App\Shared\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Registers the Newsletter Marketing module (subscriber groups, reusable
 * templates, campaigns, scheduled/queued sending, suppression list,
 * delivery reports) with the platform via config('modules.enabled'), per
 * docs/ARCHITECTURE.md §5. Builds on the pre-existing footer signup
 * (App\Models\NewsletterSubscriber, App\Actions\Newsletter, the SES
 * webhook) rather than duplicating it. Filament discovers the module's
 * Resources/Pages from app/Modules/Newsletter/Filament automatically.
 *
 * Switched on and off only by NEWSLETTER_MARKETING_ENABLED
 * (config/newsletter.php) — see docs/NEWSLETTER-MARKETING.md. Migrations,
 * routes and the schedule entry are always registered: the tables must
 * exist either way, the unsubscribe/tracking routes must keep working for
 * email already sent, and the scheduled command checks the flag itself.
 */
class NewsletterServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Newsletter Marketing';
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'newsletter-marketing');

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchDueCampaignsCommand::class]);
        }

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command(DispatchDueCampaignsCommand::class)
                ->everyMinute()
                ->withoutOverlapping(10);
        });
    }
}
