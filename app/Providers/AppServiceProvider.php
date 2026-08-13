<?php

namespace App\Providers;

use App\Enums\EmailRecipientType;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Support\Modules\ModuleRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
