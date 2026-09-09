<?php

use App\Console\Commands\DeleteExpiredGratitudeJournalEntriesCommand;
use App\Console\Commands\PublishDuePagesCommand;
use App\Console\Commands\SendGratitudeJournalRemindersCommand;
use App\Http\Middleware\BetaAccessGate;
use App\Http\Middleware\CheckMaintenanceMode;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Temporary Beta Access Gate (App\Http\Middleware\BetaAccessGate's
        // own docblock) — appended (never prepended: the "web" group's own
        // StartSession middleware has to run first, or $request->session()
        // below throws) but listed ahead of Maintenance Mode, so an
        // un-authorized visitor never learns anything about the site beyond
        // the password page.
        //
        // Website Setup's Maintenance Mode (docs/ARCHITECTURE.md §16.3) —
        // excludes /admin/*, /livewire/*, and /up internally.
        $middleware->web(append: [
            BetaAccessGate::class,
            CheckMaintenanceMode::class,
        ]);

        // ADMIN-008: Stripe cannot supply a CSRF token — the webhook's own
        // signature verification (StripeWebhookController) is its actual
        // authenticity guarantee, the same way any webhook endpoint works.
        $middleware->validateCsrfTokens(except: [
            'commerce/webhooks/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    // ADMIN-006: flips Scheduled pages to Published once publish_at passes.
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(PublishDuePagesCommand::class)->everyFiveMinutes();

        // Gratitude Journal reminder — fixed 08:00 server time, application
        // timezone for every member alike (see the command's own docblock).
        $schedule->command(SendGratitudeJournalRemindersCommand::class)->dailyAt('08:00');

        // Gratitude Journal retention — daily cleanup of expired journal
        // entries only (source = journal); registration Light Posts are
        // never touched (see the command's own docblock). Scheduled well
        // clear of the 08:00 reminder run above.
        $schedule->command(DeleteExpiredGratitudeJournalEntriesCommand::class)->dailyAt('03:00');
    })
    ->create();
