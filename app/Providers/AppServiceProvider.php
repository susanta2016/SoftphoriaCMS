<?php

namespace App\Providers;

use App\Enums\EmailRecipientType;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Support\Modules\ModuleRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);

        $this->app->make(ModuleRegistry::class)
            ->register(config('modules.enabled', []));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->routeResetPasswordThroughEmailTemplates();
        $this->guardDestructiveMigrations();
    }

    /**
     * migrate:fresh/refresh/reset and db:wipe drop every table in whatever
     * database the current connection happens to point at — Laravel only
     * gates that behind its --force confirmation when APP_ENV=production
     * (Illuminate\Console\ConfirmableTrait), so in any other environment
     * (this project runs `local`) they execute immediately with zero
     * prompt. That's exactly how a `migrate:fresh` meant for one branch's
     * database (this project keeps a separate DB per git branch, e.g.
     * softphoria vs softphoria_jacobcms) silently wiped the wrong one on
     * 2026-08-19 — recovered from a same-instance sibling database, but
     * there is no guarantee of one next time.
     *
     * Requiring --force in `local` too costs nothing for an intentional
     * wipe (the flag already exists on these exact commands for the
     * production case) and forces a second, visible step — seeing the
     * target database name printed before it's gone — for an accidental
     * one. Deliberately NOT applied to `testing`: PHPUnit's RefreshDatabase/
     * DatabaseMigrations/DatabaseTruncation traits call `migrate:fresh`
     * internally on every test run (CanConfigureMigrationCommands::
     * migrateFreshUsing()) without --force — guarding `testing` would
     * block the entire test suite, not just an accidental manual command a
     * human never actually runs under APP_ENV=testing. Skipped outside
     * `local` entirely so CI/staging/production keep Laravel's own
     * untouched behavior (production already gets its own --force gate).
     */
    private function guardDestructiveMigrations(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'], true)) {
                return;
            }

            if ($event->input->hasParameterOption('--force')) {
                return;
            }

            $database = config('database.connections.'.config('database.default').'.database');

            $event->output->writeln(
                "<error>Refusing to run \"php artisan {$event->command}\" — it drops every table in \"{$database}\". ".
                'If that is really the database you mean to wipe, re-run with --force.</error>',
            );

            throw new RuntimeException("php artisan {$event->command} blocked without --force (database: {$database}).");
        });
    }

    /**
     * The first real (non-inert) consumer of the Email Template registry
     * (docs/ARCHITECTURE.md §16.5/§16.6) — replaces Laravel's own built-in
     * markdown content for the password_reset/user template, already
     * triggered today by SendUserPasswordResetLinkAction/GenerateNewPasswordAction
     * via Password::broker(). Falls back to Laravel's default content if the
     * template is disabled or missing: unlike a "welcome" email, password
     * reset delivery is functionally required for account recovery, so
     * "disabled" here must not silently break the reset flow.
     *
     * password.reset (the public reset-password page) doesn't exist yet —
     * AUTH-001 is a later stage — so the {{reset_url}} variable degrades to
     * config('app.url') until that route ships, guarded by Route::has()
     * rather than fabricating a fake route now.
     */
    private function routeResetPasswordThroughEmailTemplates(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $resetUrl = Route::has('password.reset')
                ? url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false))
                : config('app.url');

            $mailable = app(TemplatedMailer::class)->renderAsMailable('password_reset', EmailRecipientType::User, [
                'user_name' => $notifiable->name ?? '',
                'reset_url' => $resetUrl,
            ]);

            if ($mailable !== null) {
                return $mailable;
            }

            return (new MailMessage)
                ->subject('Reset your password')
                ->line('You are receiving this email because we received a password reset request for your account.')
                ->action('Reset Password', $resetUrl)
                ->line('If you did not request a password reset, no further action is required.');
        });
    }
}
