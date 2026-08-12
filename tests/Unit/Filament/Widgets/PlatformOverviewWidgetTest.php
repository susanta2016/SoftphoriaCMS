<?php

namespace Tests\Unit\Filament\Widgets;

use App\Filament\Widgets\PlatformOverviewWidget;
use App\Models\ContactRequest;
use App\Models\Page;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PlatformOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_reflect_existing_core_data_counts(): void
    {
        User::factory()->create(['status' => 'active']);
        User::factory()->count(2)->create(['status' => 'suspended']);

        Page::create([
            'title' => 'Published Page',
            'slug' => 'published-page',
            'template' => 'standard',
            'status' => 'published',
        ]);
        Page::create([
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'template' => 'standard',
            'status' => 'draft',
        ]);

        ContactRequest::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello there',
        ]);

        [$totalUsers, $activeUsers, $publishedPages, $newContactRequests] = $this->getStats();

        $this->assertSame('Total Users', $totalUsers->getLabel());
        $this->assertSame(3, $totalUsers->getValue());

        $this->assertSame('Active User Accounts', $activeUsers->getLabel());
        $this->assertSame(1, $activeUsers->getValue());

        $this->assertSame('Published Pages', $publishedPages->getLabel());
        $this->assertSame(1, $publishedPages->getValue());

        $this->assertSame('New Contact Requests', $newContactRequests->getLabel());
        $this->assertSame(1, $newContactRequests->getValue());
    }

    public function test_stats_are_zero_when_no_core_data_exists(): void
    {
        [$totalUsers, $activeUsers, $publishedPages, $newContactRequests] = $this->getStats();

        $this->assertSame(0, $totalUsers->getValue());
        $this->assertSame(0, $activeUsers->getValue());
        $this->assertSame(0, $publishedPages->getValue());
        $this->assertSame(0, $newContactRequests->getValue());
    }

    /**
     * @return array<Stat>
     */
    private function getStats(): array
    {
        $method = new ReflectionMethod(PlatformOverviewWidget::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke(new PlatformOverviewWidget);
    }
}
