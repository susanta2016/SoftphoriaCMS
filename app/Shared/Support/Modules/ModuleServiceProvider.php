<?php

namespace App\Shared\Support\Modules;

use Illuminate\Support\ServiceProvider;

/**
 * Base provider every Softphoria module provider must extend.
 *
 * Registering a module with the platform (see ModuleRegistry) requires
 * nothing more than a stable, human-readable module name. Modules remain
 * free to use the normal ServiceProvider register()/boot() lifecycle for
 * anything module-specific (routes, views, migrations, config, Filament
 * resources, etc.) once that module is actually implemented.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The stable, human-readable name of this module (e.g. "Music").
     */
    abstract public function moduleName(): string;
}
