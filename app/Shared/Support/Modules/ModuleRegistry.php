<?php

namespace App\Shared\Support\Modules;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Registers Softphoria module providers with the application container.
 *
 * This is intentionally the entire module system for CORE-002: a module
 * enables itself by adding its provider class to config('modules.enabled'),
 * and this registry validates and registers it. There is no filesystem
 * scanning, caching, or manifest format — that complexity is not justified
 * until real modules exist and require it.
 */
final class ModuleRegistry
{
    /**
     * Module name => provider class, for every module registered so far.
     *
     * @var array<string, class-string<ModuleServiceProvider>>
     */
    private array $registered = [];

    public function __construct(private readonly Application $app) {}

    /**
     * Register every given module provider class with the application.
     *
     * @param  array<int, class-string<ModuleServiceProvider>>  $providerClasses
     */
    public function register(array $providerClasses): void
    {
        foreach ($providerClasses as $providerClass) {
            $this->registerModule($providerClass);
        }
    }

    /**
     * The module name => provider class map of everything registered so far.
     *
     * @return array<string, class-string<ModuleServiceProvider>>
     */
    public function registered(): array
    {
        return $this->registered;
    }

    private function registerModule(string $providerClass): void
    {
        if (! is_subclass_of($providerClass, ModuleServiceProvider::class)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] must extend %s to be registered as a Softphoria module.',
                $providerClass,
                ModuleServiceProvider::class,
            ));
        }

        /** @var ModuleServiceProvider $provider */
        $provider = $this->app->register($providerClass);

        $this->registered[$provider->moduleName()] = $providerClass;
    }
}
