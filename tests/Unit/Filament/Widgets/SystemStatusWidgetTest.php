<?php

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\SystemStatusWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class SystemStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_jobs_stat_is_healthy_when_the_queue_has_no_failures(): void
    {
        $stats = $this->getStats();

        $this->assertSame('Failed Jobs', $stats[1]->getLabel());
        $this->assertSame(0, $stats[1]->getValue());
        $this->assertSame('success', $stats[1]->getColor());
    }

    public function test_failed_jobs_stat_flags_danger_when_the_queue_has_failures(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $stats = $this->getStats();

        $this->assertSame(1, $stats[1]->getValue());
        $this->assertSame('danger', $stats[1]->getColor());
    }

    public function test_queued_jobs_stat_reflects_the_jobs_table(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $stats = $this->getStats();

        $this->assertSame('Queued Jobs', $stats[0]->getLabel());
        $this->assertSame(1, $stats[0]->getValue());
    }

    /**
     * @return array<Stat>
     */
    private function getStats(): array
    {
        $method = new ReflectionMethod(SystemStatusWidget::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke(new SystemStatusWidget);
    }
}
