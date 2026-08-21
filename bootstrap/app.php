<?php

use App\Console\Commands\PublishDuePagesCommand;
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
        // Website Setup's Maintenance Mode (docs/ARCHITECTURE.md §16.3) —
        // excludes /admin/*, /livewire/*, and /up internally.
        $middleware->web(append: [
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
    })
    ->create();
