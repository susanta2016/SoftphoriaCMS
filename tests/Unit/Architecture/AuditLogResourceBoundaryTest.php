<?php

namespace Tests\Unit\Architecture;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use ReflectionClass;
use Tests\TestCase;

/**
 * ADMIN-011 is a Softphoria Core feature: nothing it introduces may live
 * under app/Modules (client-specific), and it must not introduce a second
 * audit-writing service alongside the existing
 * App\Shared\Services\AuditLogService.
 */
class AuditLogResourceBoundaryTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    private function admin011Classes(): array
    {
        return [
            AuditLog::class,
            AuditLogResource::class,
        ];
    }

    public function test_admin_011_classes_live_under_core_not_a_module(): void
    {
        foreach ($this->admin011Classes() as $class) {
            $this->assertStringStartsNotWith('App\\Modules\\', $class);
        }
    }

    public function test_admin_011_does_not_introduce_a_second_audit_writer(): void
    {
        foreach ($this->admin011Classes() as $class) {
            $source = file_get_contents((new ReflectionClass($class))->getFileName());

            $this->assertStringNotContainsString('class AuditLogService', $source);
        }
    }
}
