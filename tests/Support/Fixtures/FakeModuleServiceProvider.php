<?php

namespace Tests\Support\Fixtures;

use App\Shared\Support\Modules\ModuleServiceProvider;

/**
 * A throwaway module provider used only to exercise ModuleRegistry in
 * tests. It implements no business functionality and is not registered
 * by config/modules.php.
 */
final class FakeModuleServiceProvider extends ModuleServiceProvider
{
    public const MODULE_NAME = 'FakeTestModule';

    public function moduleName(): string
    {
        return self::MODULE_NAME;
    }
}
