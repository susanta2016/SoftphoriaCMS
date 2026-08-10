<?php

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\RecentActivityWidget;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class RecentActivityWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_query_orders_entries_most_recent_first_and_limits_to_ten(): void
    {
        foreach (range(1, 12) as $i) {
            $this->makeAuditLog([
                'action' => 'created',
                'entity_type' => 'Page',
                'entity_id' => $i,
                'created_at' => now()->subMinutes(12 - $i),
            ]);
        }

        $results = $this->getTableQuery()->get();

        $this->assertCount(10, $results);
        $this->assertSame(12, $results->first()->entity_id);
        $this->assertSame(3, $results->last()->entity_id);
    }

    public function test_table_query_eager_loads_the_acting_user(): void
    {
        $user = User::factory()->create();

        $this->makeAuditLog([
            'user_id' => $user->id,
            'action' => 'updated',
            'entity_type' => 'Page',
            'entity_id' => 1,
        ]);

        $result = $this->getTableQuery()->first();

        $this->assertTrue($result->relationLoaded('user'));
        $this->assertSame($user->id, $result->user->id);
    }

    private function getTableQuery(): Builder
    {
        $method = new ReflectionMethod(RecentActivityWidget::class, 'getTableQuery');
        $method->setAccessible(true);

        return $method->invoke(new RecentActivityWidget);
    }

    /**
     * AuditLog carries no #[Fillable] allow-list (see App\Models\AuditLog) —
     * this platform intentionally has no audit-log writer yet, so attributes
     * are set directly rather than via mass assignment.
     */
    private function makeAuditLog(array $attributes): AuditLog
    {
        $log = new AuditLog;

        foreach ($attributes as $key => $value) {
            $log->{$key} = $value;
        }

        $log->save();

        return $log;
    }
}
