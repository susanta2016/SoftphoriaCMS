<?php

namespace App\Modules\Commerce\Providers;

use App\Modules\Commerce\Services\Stripe\StripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Shared\Support\Modules\ModuleServiceProvider;
use Stripe\StripeClient;

/**
 * Registers the Commerce module (Orders, Order Items, Entitlements,
 * Subscriptions, Payment Transactions, Download log) with the platform via
 * config('modules.enabled'), per docs/ARCHITECTURE.md §5 — mirrors
 * App\Modules\Music\Providers\MusicServiceProvider. See docs/ARCHITECTURE.md
 * §17 for the module's own architectural conventions (Global Pricing as the
 * only pricing source, historical price snapshots, guest purchases,
 * subscription state, entitlements, download grants/logs, private local
 * audio storage reuse, the Stripe provider abstraction).
 */
class CommerceServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Commerce';
    }

    public function register(): void
    {
        $this->app->bind(StripeClient::class, fn () => new StripeClient((string) config('services.stripe.secret')));
        $this->app->bind(StripeGatewayContract::class, StripeGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
