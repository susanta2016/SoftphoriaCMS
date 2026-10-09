<?php

use App\Console\Commands\PruneSecurityDataCommand;
use App\Console\Commands\PublishDuePagesCommand;
use App\Http\CloudflareProxies;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureFeatureEnabled;
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

        // Cloudflare in front of the site: read the visitor's IP and the
        // https scheme from its forwarded headers — only when the request
        // really comes from Cloudflare (see App\Http\CloudflareProxies).
        // X-Forwarded-Host/Port are not trusted: Cloudflare doesn't set them.
        $middleware->trustProxies(
            at: CloudflareProxies::RANGES,
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        // feature:<key> — 404s a route whose frontend feature is switched
        // off on Admin → Website Setup → Features Activation.
        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
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
        // Privacy Policy §6: security data (IPs, user agents, IP locations) kept 12 months at most.
        $schedule->command(PruneSecurityDataCommand::class)->dailyAt('03:15');
    })
    ->create();
