<?php

namespace Tests\Unit\Architecture;

use App\Providers\AppServiceProvider;
use App\Shared\Support\Modules\ModuleRegistry;
use InvalidArgumentException;
use Tests\Support\Fixtures\FakeModuleServiceProvider;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_no_modules_are_enabled_by_default(): void
    {
        $this->assertSame([], config('modules.enabled'));
    }

    public function test_it_registers_a_valid_module_provider(): void
    {
        $registry = new ModuleRegistry($this->app);

        $registry->register([FakeModuleServiceProvider::class]);

        $this->assertSame(
            [FakeModuleServiceProvider::MODULE_NAME => FakeModuleServiceProvider::class],
            $registry->registered(),
        );
        $this->assertTrue($this->app->providerIsLoaded(FakeModuleServiceProvider::class));
    }

    public function test_it_rejects_a_provider_that_does_not_extend_module_service_provider(): void
    {
        $registry = new ModuleRegistry($this->app);

        $this->expectException(InvalidArgumentException::class);

        $registry->register([AppServiceProvider::class]);
    }

    public function test_the_application_boots_with_the_module_registry_bound_as_a_singleton(): void
    {
        $first = $this->app->make(ModuleRegistry::class);
        $second = $this->app->make(ModuleRegistry::class);

        $this->assertSame($first, $second);
    }
}
